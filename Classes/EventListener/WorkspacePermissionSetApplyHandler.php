<?php

declare(strict_types=1);

/*
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

namespace T3UX\AclEnhancements\EventListener;

use T3UX\AclEnhancements\Permission\Set\Event\RecordPermissionSetApplyEvent;
use T3UX\AclEnhancements\Permission\Set\Instructions\GetBackendPermissionInstructionInterface;

readonly class WorkspacePermissionSetApplyHandler
{
    public function applyBackendUserGroupWorkspacePermissions(RecordPermissionSetApplyEvent $event): void
    {
        $set = $event->getPermissionSet();

        if ($event->getTableName() !== 'be_groups'
            || !$set instanceof GetBackendPermissionInstructionInterface
            || ($workspaceLiveEditing = $set->getBackendPermissionInstruction()->workspaceLiveEditing) === null
            || !in_array('workspace_perms', $set->getSuitableTablesAndTableFields()['be_groups'] ?? [], true)
        ) {
            return;
        }

        $appliedRecord = $event->getAppliedRecord();
        $appliedRecord['workspace_perms'] ??= false;

        if ($workspaceLiveEditing) {
            $appliedRecord['workspace_perms'] = $workspaceLiveEditing;
        }

        $event->setAppliedRecord($appliedRecord);
    }
}
