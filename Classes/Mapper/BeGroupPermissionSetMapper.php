<?php

declare(strict_types=1);

/*
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

namespace T3UX\AclEnhancements\Mapper;

use T3UX\AclEnhancements\Contract\PermissionSetMapperInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class BeGroupPermissionSetMapper implements PermissionSetMapperInterface
{
    public function fromRow(array $row): array
    {
        $label = ($row['label'] ?? '');

        if (($row['uid'] ?? 0) > 0) {
            $uid = (int)$row['uid'];
            $label = (string)($row['title'] ?? 'be_group_' . $uid);
        }

        $tsconfig = (string)($row['TSconfig'] ?? '');

        $resources = [];

        foreach ($this->explode((string)($row['tables_select'] ?? '')) as $table) {
            if ($table !== '') {
                $resources[$table]['permissions'] = array_values(array_unique(array_merge($resources[$table]['permissions'] ?? [], ['read'])));
            }
        }

        foreach ($this->explode((string)($row['tables_modify'] ?? '')) as $table) {
            if ($table !== '') {
                $resources[$table]['permissions'] = array_values(array_unique(array_merge($resources[$table]['permissions'] ?? [], ['write'])));
            }
        }

        $nonExcludeFields = is_array($row['non_exclude_fields'] ?? '') ? $row['non_exclude_fields'] : $this->explode(
            (string)($row['non_exclude_fields'] ?? '')
        );

        foreach ($nonExcludeFields as $entry) {
            $entry = trim($entry);
            $separator = null;

            if ($entry === '') {
                continue;
            }

            if (str_contains($entry, ':')) {
                $separator = ':';
            } elseif (str_contains($entry, '.')) {
                $separator = '.';
            }

            if ($separator === null) {
                continue;
            }

            [$table, $field] = array_map('trim', explode($separator, $entry, 2));

            if ($table === '' || $field === '') {
                continue;
            }

            $resources[$table]['fields'] = array_values(array_unique(array_merge($resources[$table]['fields'] ?? [], [$field])));
        }

        $pageTypes = is_array($row['pagetypes_select'] ?? '') ? $row['pagetypes_select'] : $this->explode((string)($row['pagetypes_select'] ?? ''));

        if ($pageTypes !== []) {
            $resources['pages']['selectFieldItems']['doktype'] = $pageTypes;
        }

        $explicitAllowDeny = is_array(($row['explicit_allowdeny'] ?? '')) ? $row['explicit_allowdeny'] : explode(',', ($row['explicit_allowdeny'] ?? ''));

        foreach ($explicitAllowDeny as $allowDeny) {
            $permissionParts = explode(':', $allowDeny);
            if (count($permissionParts) !== 3) {
                continue;
            }
            [$table, $typeField, $fieldValue] = $permissionParts;
            $resources[$table]['selectFieldItems'][$typeField][] = $fieldValue;
        }

        $filePermissions = is_array($row['file_permissions'] ?? '') ? array_values($row['file_permissions']) : $this->explode((string)($row['file_permissions'] ?? ''));
        $groupMods = is_array($row['groupMods'] ?? '') ? $this->parseGroupMods(implode(',', $row['groupMods'])) : $this->parseGroupMods((string)($row['groupMods'] ?? ''));
        $languages = is_array(($row['allowed_languages'] ?? '')) ? $row['allowed_languages'] : $this->explode((string)($row['allowed_languages'] ?? ''));
        $fileMounts = is_array(($row['file_mountpoints'] ?? '')) ? $row['file_mountpoints'] : $this->csvToIntArray((string)($row['file_mountpoints'] ?? ''));
        $dbMounts = is_array(($row['db_mountpoints'] ?? '')) ? $row['db_mountpoints'] : $this->dbMountsToIntArray((string)($row['db_mountpoints'] ?? ''));
        $mfaProviders = is_array(($row['mfa_providers'] ?? '')) ? $row['mfa_providers'] : $this->explode((string)($row['mfa_providers'] ?? ''));
        $categories = is_array(($row['category_perms'] ?? '')) ? array_values($row['category_perms']) : $this->csvToIntArray((string)($row['category_perms'] ?? ''));
        $widgets = is_array(($row['availableWidgets'] ?? '')) ? array_values($row['availableWidgets']) : $this->csvToIntArray((string)($row['availableWidgets'] ?? ''));

        return [
            'label' => $label,
            'categories' => $categories,
            'customOptions' => [],
            'fileMounts' => $fileMounts,
            'filePermissions' => $filePermissions,
            'languages' => $languages,
            'mfaProviders' => $mfaProviders,
            'modules' => $groupMods,
            'resources' => $resources,
            'settings' => $tsconfig !== '' ? [$tsconfig] : new \stdClass(),
            'sites' => $dbMounts,
            'widgets' => $widgets,
            'workspaceLiveEditing' => null,
        ];
    }

    /**
     * @return array<string>
     */
    private function explode(string $csv): array
    {
        return GeneralUtility::trimExplode(',', $csv, true);
    }

    /**
     * @return array<int>
     */
    private function csvToIntArray(string $csv): array
    {
        $out = [];
        foreach ($this->explode($csv) as $v) {
            if (is_numeric($v)) {
                $out[] = (int)$v;
            }
        }
        return $out;
    }

    /**
     * @return array<int>
     */
    private function dbMountsToIntArray(string $csv): array
    {
        $out = [];
        foreach ($this->explode($csv) as $v) {
            if (is_numeric($v)) {
                $out[] = (int)$v;
            } elseif (str_starts_with($v, 'pages_')) {
                $v = str_replace('pages_', '', $v);
                $out[] = (int)$v;
            }
        }
        return $out;
    }

    /**
     * @return array<string,true>
     */
    private function parseGroupMods(string $csv): array
    {
        $out = [];
        foreach ($this->explode($csv) as $key) {
            $out[$key] = true;
        }
        return $out;
    }
}
