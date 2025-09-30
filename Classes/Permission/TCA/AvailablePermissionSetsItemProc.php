<?php

declare(strict_types=1);

/*
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

namespace T3UX\AclEnhancements\Permission\TCA;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use T3UX\AclEnhancements\Permission\PermissionSetsRegistryInterface;
use T3UX\AclEnhancements\Permission\RecordPermissionSetsApplyHandlerInterface;
use TYPO3\CMS\Core\Authentication\GroupResolver;
use TYPO3\CMS\Core\Schema\Struct\SelectItem;

/**
 * TCA column itemProc function for the `be_group.permission_sets` field to provide available
 * permission presets for the multiple side-by-side selection. This selection is used within
 * the {@see GroupResolver::fetchRowsFromDatabase()} and defines the presets which should be
 * dynamically applied using the {@see RecordPermissionSetsApplyHandlerInterface} service.
 *
 * Note that this is a special case and thus this class is narrowed down hard coded to
 * that field and will throw a \InvalidArgumentException when used for another TCA table field.
 *
 * @internal to be used in EXT:core only and not part of the public Core API.
 */
#[Autoconfigure(public: true)]
readonly class AvailablePermissionSetsItemProc
{
    public function __construct(
        private PermissionSetsRegistryInterface $permissionSets,
    ) {}

    /**
     * @param array<string,mixed> $params
     */
    public function backendGroupSelector(array &$params): void
    {
        $table = $params['table'] ?? '';
        $field = $params['field'] ?? '';
        $type = $params['config']['type'] ?? '';
        if ($table !== 'be_groups' || $field !== 'permission_sets') {
            throw new \InvalidArgumentException(
                sprintf(
                    'AvailablePermissionSetsItemProc not allowed for "%s"."%s"',
                    $table,
                    $field,
                ),
                1722855606,
            );
        }

        foreach ($this->permissionSets as $permissionSet) {
            if (!$permissionSet->permissionSet->getState()->isValid()) {
                // only valid permission sets should be shown.
                continue;
            }
            $params['items'][] = new SelectItem(
                type: $type,
                label: $permissionSet->permissionSet->getLabel(),
                value: $permissionSet->permissionSet->getIdentifier(),
            );
        }
    }
}
