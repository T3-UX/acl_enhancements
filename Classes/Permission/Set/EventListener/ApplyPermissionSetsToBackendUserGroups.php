<?php

declare(strict_types=1);

/*
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

namespace T3UX\AclEnhancements\Permission\Set\EventListener;

use T3UX\AclEnhancements\Permission\ApplyMode;
use T3UX\AclEnhancements\Permission\RecordPermissionSetsApplyHandlerInterface;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Authentication\Event\AfterGroupsResolvedEvent;
use TYPO3\CMS\Core\Authentication\GroupResolver;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;

/**
 * Listens on event(s) to apply permission sets to records.
 *
 * Reacting on the dispatched {@see AfterGroupsResolvedEvent} in {@see GroupResolver::resolveGroupsForUser()}
 * allows to minimize required services needed in {@see GroupResolver}.
 *
 * @internal core internal listener and not part of public core API
 */
readonly class ApplyPermissionSetsToBackendUserGroups
{
    public function __construct(
        private RecordPermissionSetsApplyHandlerInterface $recordPermissionSetApplyHandler,
        private TcaSchemaFactory $tcaSchemaFactory,
    ) {}

    #[AsEventListener('apply-permission-sets-to-backend-user-groups')]
    public function __invoke(AfterGroupsResolvedEvent $event): void
    {
        if ($event->getSourceDatabaseTable() !== 'be_groups'
            || !$this->tcaSchemaFactory->has('be_groups')
            || !$this->tcaSchemaFactory->get('be_groups')->hasField('permission_sets')
        ) {
            return;
        }

        $event->setGroups(
            array_map(
                fn(array $record): array => $this->recordPermissionSetApplyHandler->apply(ApplyMode::DYNAMIC, 'be_groups', $record)->record,
                $event->getGroups(),
            )
        );
    }
}
