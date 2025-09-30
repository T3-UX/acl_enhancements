<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use T3UX\AclEnhancements\Contract\PermissionSetMapperInterface;
use T3UX\AclEnhancements\Controller\PresetsModuleController;
use T3UX\AclEnhancements\EventListener\BackendUserGroupPermissionSetApplyHandler;
use T3UX\AclEnhancements\EventListener\DocheaderExportButton;
use T3UX\AclEnhancements\EventListener\WorkspacePermissionSetApplyHandler;
use T3UX\AclEnhancements\Mapper\BeGroupPermissionSetMapper;
use T3UX\AclEnhancements\Permission\Set\Event\RecordPermissionSetApplyEvent;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->public();

    $services->load('T3UX\\AclEnhancements\\', __DIR__ . '/../Classes/*')
        ->exclude([
            __DIR__ . '/../Classes/Permission/Set/Event',
            __DIR__ . '/../Classes/Event',
        ]);

    $services
        ->set(DocheaderExportButton::class)
        ->tag('event.listener', [
            'identifier' => 'acl-compare-docheader-button',
            'event' => \TYPO3\CMS\Backend\Template\Components\ModifyButtonBarEvent::class,
        ]);
    $services
        ->set(PresetsModuleController::class)
        ->public();

    $services->alias(
        PermissionSetMapperInterface::class,
        BeGroupPermissionSetMapper::class
    );

    $services->alias(
        \TYPO3\CMS\Beuser\Controller\BackendUserController::class,
        \T3UX\AclEnhancements\Xclass\BackendUserController::class
    );

    $services
        ->set(BackendUserGroupPermissionSetApplyHandler::class)
        ->tag('event.listener', [
            'event' => RecordPermissionSetApplyEvent::class,
            'method' => 'applyBackendUserGroupWidgets',
            'identifier' => 'acl.dashboard.widgets.apply',
        ]);

    $services->set(WorkspacePermissionSetApplyHandler::class)
        ->tag('event.listener', [
            'event' => RecordPermissionSetApplyEvent::class,
            'method' => 'applyBackendUserGroupWorkspacePermissions',
            'identifier' => 'acl.workspaces.apply',
        ]);
};
