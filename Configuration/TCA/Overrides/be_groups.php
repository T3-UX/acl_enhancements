<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') || die();

call_user_func(
    static function (): void {
        $GLOBALS['TCA']['be_groups']['columns']['permission_sets'] = [
            'label' => 'LLL:EXT:acl_enhancements/Resources/Private/Language/locallang_tca.xlf:be_groups.permission_sets',
            'description' => 'LLL:EXT:acl_enhancements//Resources/Private/Language/locallang_tca.xlf:be_groups.permission_sets.description',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectMultipleSideBySide',
                'itemsProcFunc' => \T3UX\AclEnhancements\Permission\TCA\AvailablePermissionSetsItemProc::class . '->backendGroupSelector',
                'size' => 5,
                'autoSizeMax' => 50,
            ],
        ];

        ExtensionManagementUtility::addToAllTCAtypes(
            'be_groups',
            'permission_sets',
            '',
            'after:subgroup'
        );
    }
);
