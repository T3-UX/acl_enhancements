<?php

declare(strict_types=1);

/*
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

namespace T3UX\AclEnhancements\Writer;

use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Yaml\Yaml;
use T3UX\AclEnhancements\Event\PermissionSetChangedEvent;
use T3UX\AclEnhancements\Permission\PermissionSetRegistryInfo;
use T3UX\AclEnhancements\Permission\PermissionSetsRegistry;
use T3UX\AclEnhancements\Permission\Provider\YamlPermissionSetsProvider;
use T3UX\AclEnhancements\Permission\Set\PermissionSetFactory;
use T3UX\AclEnhancements\Permission\Set\PermissionSetSource;
use T3UX\AclEnhancements\Permission\Set\PermissionSetState;
use T3UX\AclEnhancements\Utility\YamlFileUtility;
use TYPO3\CMS\Core\Configuration\Features;
use TYPO3\CMS\Core\Configuration\Loader\YamlFileLoader;

readonly class YamlPermissionSetsWriter implements PermissionSetsWriterInterface
{
    public const FILE_EXTENSION = 'yaml';
    public const DEFAULT_SUB_DIR = 'permission-sets';
    public const DEFAULT_PACKAGE = 'system';
    public const PROVIDER_PREFIX = 'typo3-yaml-provider';

    public function __construct(
        protected EventDispatcherInterface $eventDispatcher,
        protected YamlFileLoader $fileLoader,
        protected PermissionSetFactory $permissionSetFactory,
        protected PermissionSetsRegistry $permissionSetsRegistry,
        protected Features $features,
    ) {}

    /**
     * @param array<int|string, mixed>|null $row
     */
    public function saveToInstance(?array $row = [], string $newFilename = '', ?PermissionSetRegistryInfo $permissionSet = null): PermissionSetRegistryInfo
    {
        if ($row === null || $row === []) {
            throw new \RuntimeException('Cannot save empty record');
        }

        $newFilename = YamlFileUtility::sanitizeFilename($newFilename);

        $newFullIdentifier = YamlFileUtility::normalizeIdentifierFromFilename($newFilename);

        if ($newFullIdentifier !== $permissionSet?->identifier && $this->doesFilenameExist($newFilename)) {
            throw new \RuntimeException('Target filename exist: ' . $newFilename);
        }

        if ($permissionSet === null) {
            $filename = $newFilename;
        } else {
            $filename = YamlFileUtility::translateIdentifierToFilename($permissionSet->identifier);
        }

        $filename = YamlFileUtility::sanitizeFilename($filename);

        $permissionSet = $this->write($row, $filename, true);

        if ($filename !== $newFilename) {
            $newFilename = YamlFileUtility::translateIdentifierToFilename($newFilename);
            $this->rename($permissionSet, $newFilename);
        }

        return $this->permissionSetsRegistry->get($newFullIdentifier);
    }

    /**
     * @param array<mixed> $setContents
     */
    public function write(array $setContents, string $filename, bool $overwriteFile = false): PermissionSetRegistryInfo
    {
        $file = $this->saveFile($setContents, $filename, $overwriteFile);

        $filename = $file->getBasename('.' . $file->getExtension());

        $usePermissionState = PermissionSetState::VALID;
        $rawData = $this->fileLoader->load($file->getRealPath());

        if ($rawData === [] || array_keys($rawData) === ['label']) {
            $usePermissionState = PermissionSetState::NO_DATA;
        }

        $normalizedIdentifier = YamlFileUtility::normalizeIdentifierFromFilename($filename);

        $permissionSet = $this->permissionSetFactory->create(
            new PermissionSetSource(
                state: $usePermissionState,
                identifier: $normalizedIdentifier,
                packageName: self::DEFAULT_PACKAGE,
                permissionSetProviderName: YamlPermissionSetsProvider::class,
                data: $rawData,
                additionalContextData: [
                    'scanPath' => rtrim($file->getPath(), '/'),
                    'absoluteFileName' => $file->getPathname(),
                    'relativeFileName' => $file->getRelativePathname(),
                    'tstamp' => $file->getMTime(),
                    'crdate' => $file->getCTime(),
                ],
            )
        );

        if (!$permissionSet->getState()->isValid()) {
            unlink($file->getRealPath());
            throw new \Exception('Permission set state is invalid.');
        }

        $this->eventDispatcher->dispatch(new PermissionSetChangedEvent($normalizedIdentifier));
        return $this->permissionSetsRegistry->get($normalizedIdentifier);
    }

    /**
     * @param array<int|string, mixed> $data
     */
    public function getPermissionSetContent(array $data): string
    {
        $yaml = Yaml::dump(
            $data,
            99,
            2,
            Yaml::DUMP_OBJECT_AS_MAP | Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK
        );

        return preg_replace('/(:\s*)"\\*"/', '$1*', $yaml) ?? $yaml;
    }

    public function delete(PermissionSetRegistryInfo $permissionSetRegistryInfo): void
    {
        $filePath = $permissionSetRegistryInfo->permissionSet->getSourceInfo()->getAdditionalContextData()['absoluteFileName'];
        $relativeName = $permissionSetRegistryInfo->permissionSet->getSourceInfo()->getAdditionalContextData()['relativeFileName'];

        if (!file_exists($filePath)) {
            throw new \Exception('Permission set file ' . $relativeName . ' not found.');
        }

        if (!unlink($filePath)) {
            throw new \Exception('Unable to remove ' . $permissionSetRegistryInfo->identifier);
        }

        $this->eventDispatcher->dispatch(new PermissionSetChangedEvent($permissionSetRegistryInfo->identifier));
    }

    public function rename(PermissionSetRegistryInfo $permissionSetRegistryInfo, string $newFilename): void
    {
        $filePath = $permissionSetRegistryInfo->permissionSet->getSourceInfo()->getAdditionalContextData()['absoluteFileName'];
        $relativeName = $permissionSetRegistryInfo->permissionSet->getSourceInfo()->getAdditionalContextData()['relativeFileName'];

        $newFilepath = $this->getFilepath($newFilename);
        if (!file_exists($filePath)) {
            throw new \Exception('Permission set file ' . $relativeName . ' not found.');
        }

        if (file_exists($newFilepath)) {
            throw new \Exception('Target file exist cannot change name of file ' . $relativeName . ' to ' . $newFilename);
        }

        if (!rename($filePath, $newFilepath)) {
            throw new \Exception('Unable to rename ' . $filePath);
        }

        touch($newFilepath);

        $this->eventDispatcher->dispatch(new PermissionSetChangedEvent($permissionSetRegistryInfo->identifier));
    }

    public function isEditable(PermissionSetRegistryInfo $permissionSetRegistryInfo): bool
    {
        return $permissionSetRegistryInfo->permissionSet->getSourceInfo()->getPackageName() === 'system';
    }

    public function isReadable(PermissionSetRegistryInfo $permissionSetRegistryInfo): bool
    {
        return true;
    }

    public function isRemovable(PermissionSetRegistryInfo $permissionSetRegistryInfo): bool
    {
        return $this->isEditable($permissionSetRegistryInfo);
    }

    public function doesFilenameExist(string $filename): bool
    {
        return file_exists($this->getFilepath($filename));
    }

    protected function getFilepath(string $filename): string
    {
        return YamlFileUtility::getPermissionsDirectory() . '/' . $filename . '.' . self::FILE_EXTENSION;
    }

    /**
     * @param array<mixed> $setContents
     */
    protected function saveFile(array $setContents, string $filename, bool $overwriteFile = false): SplFileInfo
    {
        $permissionsDirectory = YamlFileUtility::getPermissionsDirectory();

        if ($filename === '') {
            $defaultName = 'permission-set-' . time();

            $base = (string)($setContents['title'] ?? $defaultName);
            $sanitizedBase = YamlFileUtility::sanitizeFilename($base);

            $filename = $sanitizedBase !== '' ? $sanitizedBase : $defaultName;
        } else {
            $filename = YamlFileUtility::sanitizeFilename($filename);
        }

        $content = $this->getPermissionSetContent($setContents);

        if ($overwriteFile === true) {
            $filePath = $this->getFilepath($filename);
        } else {
            $filePath = YamlFileUtility::uniqueFilenameInPath($filename, $permissionsDirectory);
        }

        if (@file_put_contents($filePath, $content) === false) {
            throw new \RuntimeException('Cannot write file: ' . $filePath);
        }

        $finder = Finder::create()
            ->files()
            ->sortByName()
            ->depth(0)
            ->name(['/^[a-z0-9\-_]+\.yaml$/i'])
            ->in($permissionsDirectory);

        foreach ($finder as $file) {
            if (str_ends_with($filePath, $file->getFilename())) {
                return $file;
            }
        }

        throw new \Exception('File doesn\'t exist');
    }
}
