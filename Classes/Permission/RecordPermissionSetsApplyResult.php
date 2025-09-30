<?php

declare(strict_types=1);

/*
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

namespace T3UX\AclEnhancements\Permission;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Result information returned by {@see RecordPermissionSetsApplyHandlerInterface::apply()}.
 *
 * @internal This is an experimental implementation and might change in TYPO3 v14.
 */
#[Exclude]
readonly class RecordPermissionSetsApplyResult
{
    /**
     * @param array<string, mixed> $original The original record.
     * @param array<string, mixed> $record The changed record containing the applied permission values.
     * @param string[] $appliedFields Field names which has changed in record.
     * @param string[] $appliedPermissionSets Identifiers of applied sets.
     * @param string[] $skippedPermissionSets Identifiers of skipped sets, either not found or invalid.
     */
    public function __construct(
        public ApplyMode $mode,
        public string $tableName,
        public array $original,
        public array $record,
        public bool $recordHasBeenModified,
        public array $appliedFields,
        public array $appliedPermissionSets,
        public array $skippedPermissionSets,
    ) {}
}
