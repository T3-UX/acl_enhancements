<?php

declare(strict_types=1);

/*
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

namespace T3UX\AclEnhancements\Form\FieldInformation;

use T3UX\AclEnhancements\Permission\PermissionSetRegistryInfo;
use T3UX\AclEnhancements\Permission\PermissionSetsRegistry;
use TYPO3\CMS\Backend\Form\AbstractNode;
use TYPO3\CMS\Core\Localization\LanguageService;

/**
 * @internal for backend internal usage only and not part of public API.
 */
class PermissionSetManagedInformation extends AbstractNode
{
    public function __construct(
        protected PermissionSetsRegistry $registry
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function render(): array
    {
        $table = $this->data['tableName'] ?? '';
        $permissionIds = [];

        if ($table === 'be_groups') {
            $fieldName = (string)($this->data['fieldName'] ?? '');

            foreach (($this->data['databaseRow']['permission_sets'] ?? []) as $permissionSetIdentifier) {
                $permissionSetRegistryInfo = $this->registry->get($permissionSetIdentifier);

                if (in_array($permissionSetIdentifier, $permissionIds, true)) {
                    continue;
                }

                $fieldHasData = $this->findIfPermissionSetIsInUse($permissionSetRegistryInfo, $fieldName);

                if ($fieldHasData !== false) {
                    $permissionIds[] = $permissionSetIdentifier;
                }
            }
        }
        $resultArray = $this->initializeResultArray();

        if ($permissionIds === []) {
            $permissionIds[] = 'unknown-error';
        }

        $resultArray['html'] = '
            <div class="callout callout-info">
                <div class="callout-content">
                    <div class="callout-body">
                        ' . $this->getLanguageService()->sL(
            $this->data['renderData']['fieldInformationOptions']['translationKey'] ?? 'LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:formengine.permissionSetManagedField'
        ) . implode(', ', $permissionIds) . '.
                    </div>
                </div>
            </div>';
        return $resultArray;
    }

    protected function findIfPermissionSetIsInUse(PermissionSetRegistryInfo $registryInfo, string $fieldName): bool
    {
        $resourcesFields = [
            'non_exclude_fields',
            'pagetypes_select',
            'tables_select',
            'explicit_allowdeny',
            'tables_modify',
        ];

        $map = [
            'category_perms' => 'categories',
            'file_permissions' => 'filePermissions',
            'file_mountpoints' => 'fileMounts',
            'allowed_languages' => 'languages',
            'mfa_providers' => 'mfaProviders',
            'groupMods' => 'modules',
            'TSconfig' => 'settings',
            'db_mountpoints' => 'sites',
            'availableWidgets' => 'widgets',
        ];

        $data = $registryInfo->permissionSet->getSourceInfo()->getData();

        if (in_array($fieldName, $resourcesFields, true)) {
            $resources = $data['resources'] ?? [];
            if ($resources === []) {
                return false;
            }
            switch ($fieldName) {
                case 'non_exclude_fields':
                    return count(array_column($resources, 'selectFieldItems')) > 0;
                case 'pagetypes_select':
                    $isArray = is_array($resources['pages']['selectFieldItems']['doktype'] ?? '');
                    return $isArray && count($resources['pages']['selectFieldItems']['doktype']) > 0;
                case 'tables_select':
                case 'tables_modify':
                    unset($resources['pages']);
                    return count(array_column($resources, 'permissions')) > 0;
                case 'explicit_allowdeny':
                    return count(array_column($resources, 'fields')) > 0;
            }
        }

        $mappedField = $map[$fieldName] ?? false;
        return ($data[$mappedField] ?? false) !== false;
    }

    public function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
