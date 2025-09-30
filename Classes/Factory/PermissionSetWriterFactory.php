<?php

declare(strict_types=1);

/*
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

namespace T3UX\AclEnhancements\Factory;

use Psr\EventDispatcher\EventDispatcherInterface;
use T3UX\AclEnhancements\Event\FetchPermissionSetWritersEvent;
use T3UX\AclEnhancements\Permission\PermissionSetRegistryInfo;
use T3UX\AclEnhancements\Writer\PermissionSetsWriterInterface;

class PermissionSetWriterFactory
{
    public function __construct(
        protected readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public function getWriterForPermissionSet(PermissionSetRegistryInfo $permissionSet): ?PermissionSetsWriterInterface
    {
        $event = new FetchPermissionSetWritersEvent($permissionSet);
        /** @var FetchPermissionSetWritersEvent $event */
        $event = $this->eventDispatcher->dispatch($event);

        return $event->getWriter();
    }
}
