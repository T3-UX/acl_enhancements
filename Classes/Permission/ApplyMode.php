<?php

declare(strict_types=1);

/*
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

namespace T3UX\AclEnhancements\Permission;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use TYPO3\CMS\Core\DataHandling\DataHandler;

/**
 * Provides possible permission set modes, used in {@see RecordPermissionSetsApplyHandlerInterface::apply()}.
 *
 * Note that TYPO3 currently only implements {@see ApplyMode::DYNAMIC} but provides the {@see ApplyMode::PERSIST} mode
 * in case extension authors want to use {@see RecordPermissionSetsApplyHandlerInterface::apply()} in extension code to
 * directly apply changes to records for persisting, for example as part of a {@see DataHandler} hook. Thus, providing
 * the additional mode mitigates the need for extension authors to implement a custom handler implementation based on
 * the {@see RecordPermissionSetsApplyHandlerInterface}.
 *
 * @internal This is an experimental implementation and might change in the future.
 */
#[Exclude]
enum ApplyMode: string
{
    case DYNAMIC = 'dynamic';
    case PERSIST = 'persist';
}
