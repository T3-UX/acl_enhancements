<?php

declare(strict_types=1);

/*
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

namespace T3UX\AclEnhancements\Permission\Set\Instructions;

use T3UX\AclEnhancements\Permission\Set\PermissionSetSourceInterface;

/**
 * Factory interface to create BackendPermissionInstruction.
 *
 * @internal This is an experimental implementation and might change in TYPO3 v14.
 */
interface BackendPermissionInstructionFactoryInterface
{
    public function create(PermissionSetSourceInterface $source): BackendPermissionInstruction;
}
