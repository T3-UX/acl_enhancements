<?php

declare(strict_types=1);

/*
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

namespace T3UX\AclEnhancements\Permission;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use T3UX\AclEnhancements\Permission\Set\PermissionSetInterface;

/**
 * Uses as an information container within the {@see PermissionSetsRegistry}
 * implementation to have metadata around a loaded permission set.
 *
 * {@see PermissionSetsRegistry::fetchSetsFromPermissionSetProviders()}
 *
 * Custom {@see PermissionSetsRegistryInterface} implementation must use
 * this concrete class to decorate a {@see PermissionSetInterface} object.
 *
 * @internal This is an experimental implementation and might change in TYPO3 v14.
 */
#[Exclude]
readonly class PermissionSetRegistryInfo
{
    public function __construct(
        public string $identifier,
        public string $providerIdentifier,
        public PermissionSetInterface $permissionSet,
    ) {}

    /**
     * Required for code cache handling.
     */
    public static function __set_state(array $state): self
    {
        return new self(...$state);
    }
}
