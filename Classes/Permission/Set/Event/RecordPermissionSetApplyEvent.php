<?php

declare(strict_types=1);

/*
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

namespace T3UX\AclEnhancements\Permission\Set\Event;

use T3UX\AclEnhancements\Permission\ApplyMode;
use T3UX\AclEnhancements\Permission\RecordPermissionSetsApplyHandler;
use T3UX\AclEnhancements\Permission\Set\PermissionSetInterface;

/**
 * Default {@see RecordPermissionSetsApplyHandler} dispatches this event for each user backend group
 * database row in {@see RecordPermissionSetsApplyHandler::applyPermissionSet()} after default apply
 * processing to allow extension authors to apply further changes on the group record.
 *
 * This event is dispatched for each group record and permission set.
 *
 * @internal This is an experimental implementation and might change in TYPO3 v14.
 */
class RecordPermissionSetApplyEvent
{
    /**
     * @param array<string, mixed> $rawRecord Raw database record, readonly.
     * @param array<string, mixed> $appliedRecord Group record with applied changes.
     */
    public function __construct(
        private readonly ApplyMode $applyMode,
        private readonly string $tableName,
        private readonly PermissionSetInterface $permissionSet,
        private readonly array $rawRecord,
        private array $appliedRecord,
    ) {}

    /**
     * ApplyMode event is dispatched for.
     */
    public function getApplyMode(): ApplyMode
    {
        return $this->applyMode;
    }

    /**
     * The table name for {@see self::getRecord()} and {@see self::getRawRecord()}.
     *
     * Can be used to determine if actions needs to be taken on the record or not.
     */
    public function getTableName(): string
    {
        return $this->tableName;
    }

    /**
     * The permission set which should be used to apply on the group record.
     *
     * Note that depending on further interface implementations the permission
     * set might be all ready been applied fully or partly.
     */
    public function getPermissionSet(): PermissionSetInterface
    {
        return $this->permissionSet;
    }

    /**
     * The original group record as reference.
     *
     * @return array<string, mixed>
     */
    public function getRawRecord(): array
    {
        return $this->rawRecord;
    }

    /**
     * Either empty or does contain already processed permission set applies.
     *
     * @return array<string, mixed>
     */
    public function getAppliedRecord(): array
    {
        return $this->appliedRecord;
    }

    /**
     * Event listener implementation can use this to set the changed
     * record after processing custom manipulation on the record.
     *
     * @param array<string, mixed> $appliedRecord
     */
    public function setAppliedRecord(array $appliedRecord): void
    {
        $this->appliedRecord = $appliedRecord;
    }
}
