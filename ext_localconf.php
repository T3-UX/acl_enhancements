<?php

defined('TYPO3') or die();
call_user_func(
    static function (): void {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1725900005] = [
            'nodeName' => 'permissionSetManagedInformation',
            'priority' => 70,
            'class' => T3UX\AclEnhancements\Form\FieldInformation\PermissionSetManagedInformation::class,
        ];

        $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['formDataGroup']['tcaDatabaseRecord'][T3UX\AclEnhancements\Form\FormDataProvider\DatabaseRowBackendPermission::class] = [
            'depends' => [
                TYPO3\CMS\Backend\Form\FormDataProvider\DatabaseRecordOverrideValues::class,
            ],
            'before' => [
                TYPO3\CMS\Backend\Form\FormDataProvider\SiteResolving::class,
            ],
        ];

        $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Beuser\Controller\BackendUserController::class] = [
            'className' => \T3UX\AclEnhancements\Xclass\BackendUserController::class,
        ];
    }
);
