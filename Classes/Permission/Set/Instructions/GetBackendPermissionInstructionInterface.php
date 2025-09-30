<?php

declare(strict_types=1);

/*
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

namespace T3UX\AclEnhancements\Permission\Set\Instructions;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use T3UX\AclEnhancements\Permission\Set\PermissionSetInterface;

/**
 * Default {@see RecordPermissionSetsApplyHandler} implementation can apply settings based on the
 * {@see BackendPermissionInstruction} which can be provided using this interface for custom
 * {@see PermissionSetInterface} implementation.
 *
 * {@see GetAllowedWidgetsTrait} can be used to provide the default TYPO3 implementation to satisfy this interface.
 *
 * @internal This is an experimental implementation and might change in TYPO3 v14.
 */
#[Exclude]
interface GetBackendPermissionInstructionInterface
{
    public function getBackendPermissionInstruction(): BackendPermissionInstructionInterface;
}
