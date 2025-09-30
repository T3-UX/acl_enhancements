<?php

declare(strict_types=1);

/*
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

namespace T3UX\AclEnhancements\Permission\Set\Exception;

use Psr\Container\NotFoundExceptionInterface;
use TYPO3\CMS\Core\Exception;

/**
 * {@see PermissionSetsRegistry::get()} throws this exception if no permission preset
 * could be found for the provided identifier.
 *
 * Custom container implementation based on {@see PermissionSetsRegistryInterface}
 * can use this exception also for a custom invalid provider registration if needed.
 *
 * @internal This is an experimental implementation and might change in TYPO3 v14.
 */
class PermissionSetNotFoundException extends Exception implements NotFoundExceptionInterface {}
