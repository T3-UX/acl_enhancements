<?php

use T3UX\AclEnhancements\Controller\PresetsModuleController;

return [
    'acl_enhancements_module' => [
        'parent' => 'system',
        'iconIdentifier' => 'tx-acl-enhancements',
        'access' => 'group,admin',
        'path' => '/module/acl_enhancements',
        'extensionName' => 'AclEnhancements',
        'labels' => 'LLL:EXT:acl_enhancements/Resources/Private/Language/acl_module.xlf',
        'controllerActions' => [
            PresetsModuleController::class => 'index, create, duplicate, delete, edit, savePreset, download, view',
        ],
        'routes' => [
            '_default' => [
                'target' => PresetsModuleController::class . '::indexAction',
            ],
        ],
    ],
];
