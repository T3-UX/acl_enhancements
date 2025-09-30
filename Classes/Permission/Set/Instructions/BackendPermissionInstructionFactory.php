<?php

declare(strict_types=1);

/*
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

namespace T3UX\AclEnhancements\Permission\Set\Instructions;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use T3UX\AclEnhancements\Permission\Set\PermissionSetSourceInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * Default factory to create BackendPermissionInstruction.
 *
 * @internal This is an experimental implementation and might change in TYPO3 v14.
 */
#[AsAlias(id: BackendPermissionInstructionFactoryInterface::class)]
class BackendPermissionInstructionFactory implements BackendPermissionInstructionFactoryInterface
{
    public function create(PermissionSetSourceInterface $source): BackendPermissionInstruction
    {
        $instructions = [
            'categories' => [],
            'customOptions' => [],
            'filePermissions' => [],
            'fileMounts' => [],
            'languages' => [],
            'mfaProviders' => [],
            'modules' => [],
            'resources' => [],
            'settings' => [],
            'sites' => [],
            'widgets' => [],
            'workspaceLiveEditing' => null,
        ];
        if (!$source->getState()->isValid()) {
            return new BackendPermissionInstruction(...$instructions);
        }
        $data = $source->getData();
        $instructions = $this->prepareArrayStringValues($data, $instructions, 'categories');
        $instructions = $this->prepareArrayStringValues($data, $instructions, 'customOptions');
        $instructions = $this->prepareArrayStringValues($data, $instructions, 'filePermissions');
        $instructions = $this->prepareArrayIntegerValues($data, $instructions, 'fileMounts');
        $instructions = $this->prepareArrayStringValues($data, $instructions, 'languages');
        $instructions = $this->prepareArrayStringValues($data, $instructions, 'mfaProviders');
        $instructions = $this->prepareModulesInstructions($data, $instructions);
        $instructions = $this->prepareResourceInstructions($data, $instructions);
        $instructions = $this->prepareSettings($data, $instructions);
        $instructions = $this->prepareSiteInstructions($data, $instructions);
        $instructions = $this->prepareArrayStringValues($data, $instructions, 'widgets');
        $instructions = $this->prepareWorkspaceLiveEditing($data, $instructions);

        return new BackendPermissionInstruction(...$instructions);
    }

    /**
     * Prepare modules instructions compatible for {@see RecordPermissionSetsApplyHandler::processModules()}.
     */
    private function prepareModulesInstructions(array $data, array $instructions): array
    {
        $instructions['modules'] ??= [];
        if (!array_key_exists('modules', $data)) {
            return $instructions;
        }
        if (!(is_array($data['modules']) || is_string($data['modules']))) {
            return $instructions;
        }
        $modules = $data['modules'];
        if (is_string($modules)) {
            $modules = GeneralUtility::trimExplode(',', $modules, true);
        }
        if ($modules === []) {
            return $instructions;
        }
        if ($modules === ['*']) {
            $instructions['modules'] = ['*'];
            return $instructions;
        }
        foreach ($modules as $moduleName => $moduleValue) {
            if (!is_string($moduleName)
                || $moduleName === ''
                || !(
                    (is_bool($moduleValue) && $moduleValue)
                    || $moduleValue === '*'
                )
            ) {
                continue;
            }
            $instructions['modules'][$moduleName] = $moduleValue;
        }
        return $instructions;
    }

    /**
     * Prepare sites instruction compatible for {@see RecordPermissionSetsApplyHandler::processSites()}.
     *
     * Note that "*" is not supported for sites (`db_mountpoints`).
     */
    private function prepareSiteInstructions(array $data, array $instructions): array
    {
        $instructions['sites'] ??= [];
        if (!array_key_exists('sites', $data)
            || !is_array($data['sites'])
            || $data['sites'] === []
        ) {
            return $instructions;
        }
        foreach ($data['sites'] as $siteIdentifierOrPageId) {
            if (MathUtility::canBeInterpretedAsInteger($siteIdentifierOrPageId)) {
                $instructions['sites'] ??= [];
                $instructions['sites'][] = (int)$siteIdentifierOrPageId;
                continue;
            }
            if (!is_string($siteIdentifierOrPageId)
                || trim($siteIdentifierOrPageId, ' ') === ''
            ) {
                continue;
            }
            $instructions['sites'] ??= [];
            $instructions['sites'][] = $siteIdentifierOrPageId;
        }
        return $instructions;
    }

    /**
     * Prepare resource instructions compatible for:
     *
     * - {@see RecordPermissionSetsApplyHandler::processFieldPermissions()}
     * - {@see RecordPermissionSetsApplyHandler::processPageTypePermissions()}
     * - {@see RecordPermissionSetsApplyHandler::processContentTypePermissions()}
     */
    private function prepareResourceInstructions(array $data, array $instructions): array
    {
        $instructions['resources'] ??= [];
        if (!array_key_exists('resources', $data)
            || !is_array($data['resources'])
            || $data['resources'] === []
        ) {
            return $instructions;
        }
        $resources = [];
        foreach ($data['resources'] as $tableName => $config) {
            if (!is_string($tableName)
                || trim($tableName, ' ') === ''
                || !is_array($config)
                || $config === []
            ) {
                continue;
            }
            $tableInstructions = [];
            $permissions = $config['permissions'] ?? null;
            if (is_string($permissions)) {
                $permissions = GeneralUtility::trimExplode(',', $permissions, true);
            }
            if (is_array($permissions) && $permissions !== []) {
                if ($permissions === ['*']) {
                    $tableInstructions['permissions'] = ['*'];
                } else {
                    $tableInstructions['permissions'] = [];
                    foreach ($permissions as $permission) {
                        if (!is_string($permission)
                            || trim($permission, ', ') === ''
                            || str_contains($permission, ',')
                        ) {
                            continue;
                        }
                        $tableInstructions['permissions'][] = trim($permission, ', ');
                    }
                }
            }
            $fields = $config['fields'] ?? null;
            if (is_string($fields)) {
                $fields = GeneralUtility::trimExplode(',', $fields, true);
            }
            if (is_array($fields) && $fields !== []) {
                if ($fields === ['*']) {
                    $tableInstructions['fields'] = ['*'];
                } else {
                    $tableInstructions['fields'] = [];
                    foreach ($fields as $field) {
                        if (!is_string($field)
                            || trim($field, ', ') === ''
                            || str_contains($field, ',')
                        ) {
                            continue;
                        }
                        $tableInstructions['fields'][] = trim($field, ', ');
                    }
                }
            }
            $types = $config['selectFieldItems'] ?? null;
            if (is_string($types)) {
                $types = GeneralUtility::trimExplode(',', $types, true);
            }
            if (is_array($types) && $types !== []) {
                if ($types === ['*']) {
                    $tableInstructions['selectFieldItems'] = ['*'];
                } else {
                    $tableInstructions['selectFieldItems'] = [];
                    foreach ($types as $typeField => $typeFieldTypes) {
                        if (is_string($typeFieldTypes)) {
                            $typeFieldTypes = GeneralUtility::trimExplode(',', $typeFieldTypes, true);
                        }
                        if (!is_array($typeFieldTypes) || $typeFieldTypes === []) {
                            continue;
                        }
                        if ($typeFieldTypes === ['*']) {
                            $tableInstructions['selectFieldItems'][$typeField] = ['*'];
                            continue;
                        }
                        // Only associative array allowed, after `*` value has been handled above.
                        if (!is_string($typeField)
                            || MathUtility::canBeInterpretedAsInteger($typeField)
                        ) {
                            continue;
                        }
                        foreach ($typeFieldTypes as $typeFieldType) {
                            $typeFieldType = (string)$typeFieldType;
                            if (trim($typeFieldType, ', ') === '') {
                                continue;
                            }
                            $tableInstructions['selectFieldItems'][$typeField] ??= [];
                            $tableInstructions['selectFieldItems'][$typeField][] = trim($typeFieldType, ', ');
                        }
                    }
                }
            }
            if ($tableInstructions !== []) {
                $resources[$tableName] = $tableInstructions;
            }
        }
        if ($resources !== []) {
            $instructions['resources'] = $resources;
        }
        return $instructions;
    }

    /**
     * Generic helper method to provide a string-value list array, with the ability
     * to replace use `$identifier` as key for the extracted `$instructions`.
     */
    private function prepareArrayStringValues(array $data, array $instructions, string $identifier): array
    {
        $instructions[$identifier] ??= [];
        if (!array_key_exists($identifier, $data)) {
            $instructions[$identifier] ??= [];
            return $instructions;
        }
        // Handle special "*" character and skip other values as they are included anyway.
        if ($data[$identifier] === '*') {
            $instructions[$identifier] = ['*'];
            return $instructions;
        }
        if (!is_array($data[$identifier]) || $data[$identifier] === []) {
            return $instructions;
        }
        foreach ($data[$identifier] as $value) {
            if (is_int($value)) {
                $value = (string)$value;
            }
            // Array value item "*" is not removed here and handled later by apply process method, usually
            // taking it as invalid value, ignoring it but still mark field as managed as intended behaviour.
            if (!is_string($value) || trim($value, ' ') === '') {
                continue;
            }
            $value = trim($value, ' ');
            $instructions[$identifier][] = $value;
        }
        return $instructions;
    }

    /**
     * Generic helper method to provide an integer-value list array, with the ability to
     * replace use `$instructionIdentifier` as key for the extracted `$instructions`.
     */
    private function prepareArrayIntegerValues(array $data, array $instructions, string $identifier): array
    {
        $instructions[$identifier] ??= [];
        if (!array_key_exists($identifier, $data)
            || !is_array($data[$identifier])
            || $data[$identifier] === []
        ) {
            $instructions[$identifier] ??= [];
            return $instructions;
        }
        foreach ($data[$identifier] as $value) {
            if (is_bool($value)) {
                continue;
            }
            if (!MathUtility::canBeInterpretedAsInteger($value)) {
                continue;
            }
            $instructions[$identifier][] = (int)$value;
        }
        return $instructions;
    }

    /**
     * Prepare workspace live editing permission for `EXT:workspaces` {@see RecordPermissionSetApplyEvent} event handler.
     */
    private function prepareWorkspaceLiveEditing(array $data, array $instructions): array
    {
        $instructions['workspaceLiveEditing'] = $data['workspaceLiveEditing'] ?? null;
        return $instructions;
    }

    /**
     * Prepare `TSconfig` settings values used in {@see RecordPermissionSetsApplyHandler::processSettings()}.
     */
    private function prepareSettings(array $data, array $instructions): array
    {
        if (!array_key_exists('settings', $data) || !is_array($data['settings'])) {
            $instructions['settings'] ??= [];
            return $instructions;
        }
        $instructions['settings'] = $data['settings'];
        return $instructions;
    }
}
