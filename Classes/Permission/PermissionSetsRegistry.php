<?php

declare(strict_types=1);

/*
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

namespace T3UX\AclEnhancements\Permission;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use T3UX\AclEnhancements\Event\PermissionSetChangedEvent;
use T3UX\AclEnhancements\Permission\Provider\Exception\InvalidPermissionSetsProvider;
use T3UX\AclEnhancements\Permission\Provider\PermissionSetsProviderInterface;
use T3UX\AclEnhancements\Permission\Set\Exception\PermissionSetNotFoundException;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Cache\Event\CacheWarmupEvent;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Cache\Frontend\PhpFrontend;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * Container providing access to all permission sets.
 *
 * This is a container which provides access to all permission presets
 * loaded based on available {@see PermissionSetsProviderInterface}.
 *
 * @implements PermissionSetsRegistryInterface<non-empty-string, PermissionSetRegistryInfo>
 *
 * @internal This is an experimental implementation and might change in TYPO3 v14.
 */
#[AsAlias(id: PermissionSetsRegistryInterface::class)]
readonly class PermissionSetsRegistry implements PermissionSetsRegistryInterface
{
    /**
     * @param array<PermissionSetsProviderInterface> $providers
     */
    public function __construct(
        #[Autowire(expression: 'service("package-dependent-cache-identifier").withPrefix("PermissionSets").toString()')]
        private string $cacheIdentifier,
        #[Autowire(service: 'cache.core')]
        private PhpFrontend $cache,
        #[Autowire(service: 'cache.runtime')]
        private FrontendInterface $runtimeCache,
        #[AutowireIterator(tag: 'permission-sets.provider')]
        private iterable $providers,
    ) {
        foreach ($providers as $provider) {
            if (!$provider instanceof PermissionSetsProviderInterface) {
                throw new InvalidPermissionSetsProvider(
                    sprintf(
                        'Provider "%s" is not a valid PermissionSetProviderInterface implementation',
                        $provider::class,
                    ),
                    1722855602,
                );
            }
        }
    }

    /**
     * Container has a {@see PermissionSetRegistryInfo} instance for `$identifier`.
     *
     * @param string $identifier {@see PermissionSetInterface::getIdentifier()}
     * @return bool
     */
    public function has(string $identifier): bool
    {
        return $identifier !== '' && isset($this->getIterator()[$identifier]) && $this->getIterator()[$identifier] instanceof PermissionSetRegistryInfo;
    }

    /**
     * Returns the {@see PermissionSetRegistryInfo} for `$identifier`.
     *
     * @param non-empty-string $identifier {@see PermissionSetInterface::getIdentifier()}
     * @return PermissionSetRegistryInfo
     * @throws PermissionSetNotFoundException
     */
    public function get(string $identifier): PermissionSetRegistryInfo
    {
        if (!$this->has($identifier)) {
            throw new PermissionSetNotFoundException(
                sprintf('Permission set %s does not exist!', $identifier),
                1722855600,
            );
        }
        return $this->getIterator()[$identifier];
    }

    /**
     * @return array<string, PermissionSetRegistryInfo>|null
     */
    private function getFromRuntimeCache(): ?array
    {
        $entry = $this->runtimeCache->get($this->cacheIdentifier);
        return is_array($entry) ? $entry : null;
    }

    /**
     * @return array<string, PermissionSetRegistryInfo>|null
     */
    private function getFromCodeCache(): ?array
    {
        $permissionSets = $this->cache->require($this->cacheIdentifier);
        if (!is_array($permissionSets)) {
            return null;
        }
        $this->runtimeCache->set($this->cacheIdentifier, $permissionSets);

        return $permissionSets;
    }

    /**
     * @return array<string, PermissionSetRegistryInfo>
     */
    private function fetchSetsFromPermissionSetProviders(): array
    {
        /** @var array<string, PermissionSetRegistryInfo> $sets */
        $sets = [];
        foreach ($this->providers as $index => $provider) {
            $providerIdentifier = $provider::class;
            if (!MathUtility::canBeInterpretedAsInteger($index)
                && is_string($index)
                && trim($index, ' ') !== ''
            ) {
                $providerIdentifier = trim($index, ' ');
            }
            foreach ($provider->getSets() as $set) {
                if (($sets[$set->getIdentifier()] ?? null) instanceof PermissionSetRegistryInfo) {
                    throw new \RuntimeException(
                        sprintf(
                            '"%s" provided preset "%s" which has been already loaded by "%s"',
                            $provider::class,
                            $set->getIdentifier(),
                            $sets[$set->getIdentifier()]->providerIdentifier,
                        ),
                        1726401850,
                    );
                }
                $sets[$set->getIdentifier()] = new PermissionSetRegistryInfo(
                    identifier: $set->getIdentifier(),
                    providerIdentifier: $providerIdentifier,
                    permissionSet: $set,
                );
            }
        }
        $this->cache->set($this->cacheIdentifier, 'return ' . var_export($sets, true) . ';');
        $this->runtimeCache->set($this->cacheIdentifier, $sets);
        return $sets;
    }

    /**
     * @return PermissionSetsRegistryInterface<non-empty-string, PermissionSetRegistryInfo>
     * @internal not part of public API.
     */
    public function getIterator(): \Traversable&\Countable
    {
        return new \ArrayIterator($this->getFromRuntimeCache() ?? $this->getFromCodeCache() ?? $this->fetchSetsFromPermissionSetProviders());
    }

    /**
     * @internal not part of public API.
     */
    public function count(): int
    {
        return count($this->getIterator());
    }

    #[AsEventListener('typo3-core/permission-sets-container-warmup')]
    public function warmupCaches(CacheWarmupEvent $event): void
    {
        if ($event->hasGroup('system')) {
            $this->fetchSetsFromPermissionSetProviders();
        }
    }

    #[AsEventListener(event: PermissionSetChangedEvent::class)]
    public function permissionSetsChanged(): void
    {
        $this->cache->remove($this->cacheIdentifier);
        $this->runtimeCache->remove($this->cacheIdentifier);
    }
}
