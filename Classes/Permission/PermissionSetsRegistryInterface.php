<?php

declare(strict_types=1);

/*
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

namespace T3UX\AclEnhancements\Permission;

use Psr\Container\ContainerInterface;
use T3UX\AclEnhancements\Permission\Set\Exception\PermissionSetNotFoundException;
use T3UX\AclEnhancements\Permission\Set\PermissionSetInterface;

/**
 * Defines container interface to providing access to all permission presets
 * using custom lookup services or implementations as long as the required
 * information is retrieved for the interface methods.
 *
 * Custom implementation **must** register and decorate the default service,
 * retrieving the prior defined {@see PermissionSetsRegistryInterface} and
 * call the prior container when the custom container methods cannot retrieve
 * the result by themselves.
 *
 * {@see PermissionSetsRegistry} for default implementation.
 *
 * @template TKey
 * @template-covariant TValue
 * @template-extends \IteratorAggregate<TKey, TValue>
 * @extends \IteratorAggregate<non-empty-string, PermissionSetRegistryInfo>
 *
 * @internal This is an experimental implementation and might change in TYPO3 v14.
 */
interface PermissionSetsRegistryInterface extends ContainerInterface, \IteratorAggregate, \Countable
{
    /**
     * Container has a {@see PermissionSetRegistryInfo} instance for `$identifier`.
     *
     * @param non-empty-string $identifier {@see PermissionSetInterface::getIdentifier()}
     * @return bool
     */
    public function has(string $identifier): bool;

    /**
     * Returns the {@see PermissionSetRegistryInfo} for `$identifier`.
     *
     * @param non-empty-string $identifier {@see PermissionSetInterface::getIdentifier()}
     * @return PermissionSetRegistryInfo
     * @throws PermissionSetNotFoundException
     */
    public function get(string $identifier): PermissionSetRegistryInfo;
}
