<?php

declare(strict_types=1);

/*
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

namespace T3UX\AclEnhancements\Permission;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use T3UX\AclEnhancements\Permission\Set\Event\RecordPermissionSetApplyEvent;

/**
 * Custom implementation based on this interface **must** use the Symfony DI service decorator
 * pattern to retrieve the prior service instance as constructor argument. That allows to build
 * a service chain, where each implementation can call the prior service until the default handler
 * is reached.
 *
 * Given that, the {@see RecordPermissionSetsApplyHandler} default implementation **should**
 * not be replaced and act as the last handler in the chain.
 *
 * @internal This is an experimental implementation and might change in TYPO3 v14.
 */
#[AutoconfigureTag(name: RecordPermissionSetsApplyHandlerInterface::class)]
#[Autoconfigure(public: true)]
interface RecordPermissionSetsApplyHandlerInterface
{
    /**
     * Apply permission presets to $group record.
     *
     * In case `$permissionSetIdentifierList` is provided, only these preset identifiers are applied. An empty string
     * will fallback and read `$group['permission_sets']` to apply them.
     *
     * {@see RecordPermissionSetsApplyHandler::apply()} will dispatch the {@see RecordPermissionSetApplyEvent} for
     * each {@see PermissionSetInterface}.
     *
     * If no permission preset is specified the original $group record returned.
     *
     * @param ApplyMode $mode Apply record mode giving context what happens with record later on.
     * @param array<string, mixed> $record The original group record permission sets should be applied to.
     * @param string $permissionSetsIdentifierList Specify permission presets to apply, fallbacks to `$group['permission_sets]`
     * @return RecordPermissionSetsApplyResult Result object containing the changed $record with applied permission sets values and touched fields.
     */
    public function apply(ApplyMode $mode, string $tableName, array $record, string $permissionSetsIdentifierList = ''): RecordPermissionSetsApplyResult;
}
