<?php

declare(strict_types=1);

/*
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

namespace T3UX\AclEnhancements\Permission;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use T3UX\AclEnhancements\Permission\Set\Event\RecordPermissionSetApplyEvent;
use T3UX\AclEnhancements\Permission\Set\Exception\PermissionSetCannotBeAppliedForRecordPersistingException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Default implementation for {@see RecordPermissionSetsApplyHandlerInterface}.
 *
 * Note that this implementation process apply task based on dedicated method interfaces
 * provided `\T3UX\AclEnhancements\Permission\Set\Methods\*Interfaces`.
 *
 * @internal This is an experimental implementation and might change in TYPO3 v14.
 */
#[AsAlias(id: RecordPermissionSetsApplyHandlerInterface::class, public: true)]
readonly class RecordPermissionSetsApplyHandler implements RecordPermissionSetsApplyHandlerInterface
{
    public function __construct(
        private LoggerInterface $logger,
        private PermissionSetsRegistryInterface $permissionSetsContainer,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * Apply permission presets to $group record.
     *
     * In case `$setsIdentifierList` is provided, only these preset identifiers are applied.
     * An empty string will fallback and read `$group['permission_sets']` to apply them.
     *
     * If no permission preset is specified the original $group record returned.
     *
     * @param array<non-empty-string, mixed> $record The original group record permission sets should be applied to.
     * @param string $permissionSetsIdentifierList Specify permission presets to apply, fallbacks to `$group['permission_sets]`
     * @return RecordPermissionSetsApplyResult Result object containing the changed $record with applied permission sets values and touched fields.
     */
    public function apply(ApplyMode $mode, string $tableName, array $record, string $permissionSetsIdentifierList = ''): RecordPermissionSetsApplyResult
    {
        $permissionSetsIdentifierList = trim($permissionSetsIdentifierList, ' ');
        $recordPermissionSets = trim($record['permission_sets'] ?? '', ', ');
        $permissionSets = ($mode === ApplyMode::DYNAMIC) ? $recordPermissionSets : $permissionSetsIdentifierList;
        $recordUid = (int)($record['uid'] ?? 0);
        if ($mode === ApplyMode::PERSIST && $recordPermissionSets !== '') {
            throw new PermissionSetCannotBeAppliedForRecordPersistingException(
                sprintf(
                    'Requested set(s) "%s" cannot be applied for record persisting. '
                    . 'Sets selected in record "%s" for dynamic apply: "%s".',
                    $permissionSetsIdentifierList,
                    $tableName . ($recordUid ? '[' . $recordUid . ']' : ''),
                    $recordPermissionSets,
                ),
                1727998526,
            );
        }
        // TYPO3 only implements ApplyMode::DYNAMIC and need to operate on an empty record, while ApplyMode::PERSIST
        // provides the full current record. That is required to provide a public community extension to apply presets
        // directly to an existing record merging with record values to allow using presets as composing templates and
        // not as deployable permission preset. That mode has been left out for TYPO3 v13 to avoid initial confusing,
        // but is already a valid use-case for some agencies. To reduce the work which that extension has to provide
        // or replace (not xclassing) ApplyMode is kept and reacts here differently. The extension should also act as
        // incubator to gather feedback and could be implemented in the future directly removed before opening the
        // permission preset PHP api for the public.
        $appliedRecord = ($mode === ApplyMode::PERSIST) ? $record : [];
        $appliedPermissionSets = [];
        $skippedPermissionSets = [];
        foreach (GeneralUtility::trimExplode(',', $permissionSets, true) as $permissionSetIdentifier) {
            if (!$this->permissionSetsContainer->has($permissionSetIdentifier)) {
                $skippedPermissionSets[] = $permissionSetIdentifier;
                $this->logger->error(
                    sprintf(
                        'Permission preset "%s" not found in sets container.',
                        $permissionSetIdentifier,
                    )
                );
                continue;
            }
            $set = $this->permissionSetsContainer->get($permissionSetIdentifier)->permissionSet;
            if (!$set->getState()->isValid()) {
                $skippedPermissionSets[] = $permissionSetIdentifier;
                $this->logger->error(
                    sprintf(
                        'Could not apply invalid permission preset "%s" on "%s[%s]".',
                        $set->getIdentifier(),
                        $tableName,
                        $recordUid
                    ),
                );
                continue;
            }
            if (!array_key_exists($tableName, $set->getSuitableTablesAndTableFields())) {
                $skippedPermissionSets[] = $permissionSetIdentifier;
                $this->logger->error(
                    sprintf(
                        'Permission preset "%s" not suitable for "%s[%s]", skipped.',
                        $set->getIdentifier(),
                        $tableName,
                        $recordUid
                    ),
                );
                continue;
            }
            $appliedRecord = $this->eventDispatcher->dispatch(new RecordPermissionSetApplyEvent(
                applyMode: $mode,
                tableName: $tableName,
                permissionSet: $set,
                rawRecord: $record,
                appliedRecord: $appliedRecord,
            ))->getAppliedRecord();
            $appliedPermissionSets[] = $permissionSetIdentifier;
        }
        return new RecordPermissionSetsApplyResult(
            mode: $mode,
            tableName: $tableName,
            original: $record,
            record: array_replace($record, $appliedRecord),
            recordHasBeenModified: $appliedRecord !== [],
            appliedFields: array_keys($appliedRecord),
            appliedPermissionSets: $appliedPermissionSets,
            skippedPermissionSets: $skippedPermissionSets,
        );
    }
}
