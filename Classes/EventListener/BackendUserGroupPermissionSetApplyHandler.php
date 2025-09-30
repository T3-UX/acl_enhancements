<?php

declare(strict_types=1);

/*
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

namespace T3UX\AclEnhancements\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use T3UX\AclEnhancements\Permission\Set\Event\RecordPermissionSetApplyEvent;
use T3UX\AclEnhancements\Permission\Set\Instructions\GetBackendPermissionInstructionInterface;
use T3UX\AclEnhancements\Permission\Set\PermissionSetInterface;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Dashboard\WidgetRegistry;

readonly class BackendUserGroupPermissionSetApplyHandler
{
    public function __construct(
        #[Autowire(service: 'cache.runtime')]
        private FrontendInterface $runtimeCache,
        private LoggerInterface $logger,
        private WidgetRegistry $widgetRegistry,
    ) {}

    public function applyBackendUserGroupWidgets(RecordPermissionSetApplyEvent $event): void
    {
        $set = $event->getPermissionSet();

        if ($event->getTableName() !== 'be_groups'
            || !$set instanceof GetBackendPermissionInstructionInterface
            || $set->getBackendPermissionInstruction()->widgets === []
            || !in_array('availableWidgets', $set->getSuitableTablesAndTableFields()['be_groups'] ?? [], true)
        ) {
            return;
        }

        $appliedRecord = $event->getAppliedRecord();
        $appliedRecord['availableWidgets'] = $this->expandWidgetInstruction(
            $set,
            $appliedRecord['availableWidgets'] ?? ''
        );
        $event->setAppliedRecord($appliedRecord);
    }

    /**
     * @param PermissionSetInterface&GetBackendPermissionInstructionInterface $permissionSet
     * @param array<mixed>|string $currentWidgets
     * @return array<mixed>|string
     */
    private function expandWidgetInstruction(
        PermissionSetInterface&GetBackendPermissionInstructionInterface $permissionSet,
        array|string $currentWidgets,
    ): array|string {
        $verified = [];
        $widgets = is_array($currentWidgets) ? $currentWidgets : GeneralUtility::trimExplode(',', $currentWidgets, true);
        $allWidgets = $this->getAllWidgets();
        $allowedWidgets = $permissionSet->getBackendPermissionInstruction()->widgets;

        if ($allowedWidgets === ['*']) {
            return is_array($currentWidgets) ? $allWidgets : implode(',', $allWidgets);
        }

        foreach ($allowedWidgets as $dashboardWidget) {
            if (!in_array($dashboardWidget, $allWidgets, true)) {
                $this->logger->warning(
                    sprintf(
                        '[%s] Invalid widget "%s". Skipping.',
                        $permissionSet->getIdentifier(),
                        $dashboardWidget,
                    )
                );
                continue;
            }
            $verified[] = $dashboardWidget;
        }

        $resultWidgets = array_unique(array_merge($verified, $widgets));
        return is_array($currentWidgets) ? $resultWidgets : implode(',', $resultWidgets);
    }

    /** @return string[] */
    private function getAllWidgets(): array
    {
        $cacheIdentifier = 'permission-set-available-widgets';
        $widgets = $this->runtimeCache->get($cacheIdentifier);
        if (is_array($widgets)) {
            return $widgets;
        }
        $widgets = array_keys($this->widgetRegistry->getAllWidgets());
        $this->runtimeCache->set($cacheIdentifier, $widgets);
        return $widgets;
    }
}
