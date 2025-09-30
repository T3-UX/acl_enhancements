<?php

declare(strict_types=1);

/*
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

namespace T3UX\AclEnhancements\Permission\Provider\Exception;

use T3UX\AclEnhancements\Permission\PermissionSetsRegistry;
use T3UX\AclEnhancements\Permission\PermissionSetsRegistryInterface;
use T3UX\AclEnhancements\Permission\Provider\PermissionSetsProviderInterface;
use TYPO3\CMS\Core\Exception as CoreException;

/**
 * {@see PermissionSetsRegistry} throws this exception if a service is provided
 * which does not implement the {@see PermissionSetsProviderInterface}. That can
 * happen if a service is manually tagged using tag 'permission_set.provider'
 *
 * Custom container implementation based on {@see PermissionSetsRegistryInterface}
 * can use this exception also for a custom invalid provider registration if needed.
 *
 * @internal This is an experimental implementation and might change in TYPO3 v14.
 */
class InvalidPermissionSetsProvider extends CoreException {}
