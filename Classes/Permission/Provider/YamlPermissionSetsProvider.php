<?php

declare(strict_types=1);

/*
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

namespace T3UX\AclEnhancements\Permission\Provider;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Yaml\Yaml;
use T3UX\AclEnhancements\Permission\Set\PermissionSet;
use T3UX\AclEnhancements\Permission\Set\PermissionSetFactoryInterface;
use T3UX\AclEnhancements\Permission\Set\PermissionSetInterface;
use T3UX\AclEnhancements\Permission\Set\PermissionSetSource;
use T3UX\AclEnhancements\Permission\Set\PermissionSetState;
use TYPO3\CMS\Core\Configuration\Loader\YamlFileLoader;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Package\PackageManager;

/**
 * Default yaml file provider.
 *
 * This provider scans in defined paths for yaml files and uses the {@see PermissionSetFactoryInterface} to create the
 * {@see PermissionSetInterface} models.
 *
 * Note that custom factory must not use the provider schema mapping process.
 *
 * @internal This is an experimental implementation and might change in TYPO3 v14.
 */
#[AsTaggedItem(index: 'typo3/yaml', priority: 0)]
readonly class YamlPermissionSetsProvider implements PermissionSetsProviderInterface
{
    public function __construct(
        private PackageManager $packageManager,
        private LoggerInterface $logger,
        private PermissionSetFactoryInterface $permissionSetFactory,
        private YamlFileLoader $fileLoader,
    ) {}

    /**
     * Return all found yaml based presets from pre-defined extension folders or system folder.
     *
     * @return array<non-empty-string, PermissionSetInterface>
     */
    public function getSets(): array
    {
        /** @var array<non-empty-string, PermissionSetInterface> $permissionSets */
        $permissionSets = [];
        foreach ($this->packageManager->getActivePackages() as $activePackage) {
            $path = rtrim($activePackage->getPackagePath(), '/') . '/Configuration/PermissionSets/';
            if (!file_exists($path)) {
                continue;
            }
            $permissionSets = array_replace(
                $permissionSets,
                $this->scan($path, $activePackage->getValueFromComposerManifest('name')),
            );
        }
        if (file_exists($instancePath = Environment::getConfigPath() . '/permission-sets')) {
            $permissionSets = array_replace(
                $permissionSets,
                $this->scan($instancePath, 'system'),
            );
        }
        return $permissionSets;
    }

    /**
     * Scan for yaml files in `$path` and creating {@see PermissionSet} for each file.
     *
     * Note that this method **does not** search recursive in subfolders of `$path`.
     *
     * @return array<non-empty-string, PermissionSetInterface>
     */
    private function scan(string $path, string $packageName = ''): array
    {
        $permissionSets = [];
        $finder = Finder::create()
            ->files()
            ->sortByName()
            ->depth(0)
            ->name(['/^[a-z0-9\-_]+\.yaml$/i'])
            ->in($path);

        /**
         * @var SplFileInfo $file
         */
        foreach ($finder as $file) {
            $identifier = $this->normalizeIdentifier($packageName, $file);
            try {
                $usePermissionState = PermissionSetState::VALID;
                $rawData = $this->fileLoader->load($file->getPathname());
                if ($rawData === [] || array_keys($rawData) === ['label']) {
                    $usePermissionState = PermissionSetState::NO_DATA;
                }
                $permissionSets[$identifier] = $this->permissionSetFactory->create(new PermissionSetSource(
                    state: $usePermissionState,
                    identifier: $identifier,
                    packageName: $packageName,
                    permissionSetProviderName: YamlPermissionSetsProvider::class,
                    data: $rawData,
                    additionalContextData: [
                        'scanPath' => rtrim($path, '/'),
                        'absoluteFileName' => $file->getPathname(),
                        'relativeFileName' => $file->getRelativePathname(),
                        'tstamp' => $file->getMTime(),
                        'crdate' => $file->getCTime(),
                    ],
                ));
            } catch (\Throwable $e) {
                // File content cannot be loaded, indicating YAML format issues.
                // Still create a permission set without data but flagged invalid.
                $permissionSets[$identifier] = $this->permissionSetFactory->create(new PermissionSetSource(
                    state: PermissionSetState::LOADING_ERROR,
                    identifier: $identifier,
                    packageName: $packageName,
                    permissionSetProviderName: YamlPermissionSetsProvider::class,
                    data: [],
                    additionalContextData: [
                        'scanPath' => rtrim($path, '/'),
                        'absoluteFileName' => $file->getPathname(),
                        'relativeFileName' => $file->getRelativePathname(),
                        'crdate' => time(),
                        'tstamp' => time(),
                    ],
                ));
                $this->logger->error(
                    $e->getMessage(),
                    [
                        'identifier' => $identifier,
                        'setProvider' => YamlPermissionSetsProvider::class,
                        'set' => $file->getPathname(),
                        'exception' => $e,
                    ],
                );
            }
        }
        return $permissionSets;
    }

    private function normalizeIdentifier(string $packageName, \SplFileInfo $file): string
    {
        return 'typo3-yaml-provider/' . ($packageName !== '' ? $packageName . '/' : '')
            . str_replace('_', '-', $file->getBasename('.' . $file->getExtension()));
    }
}
