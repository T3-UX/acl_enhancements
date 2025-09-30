<?php

declare(strict_types=1);

/*
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

namespace T3UX\AclEnhancements\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use T3UX\AclEnhancements\Event\FetchPermissionSetWritersEvent;
use T3UX\AclEnhancements\Permission\Provider\YamlPermissionSetsProvider;
use T3UX\AclEnhancements\Writer\YamlPermissionSetsWriter;
use TYPO3\CMS\Core\Attribute\AsEventListener;

#[Autoconfigure(public: true)]
class GetPermissionSetsWritersEventListener
{
    public function __construct(private YamlPermissionSetsWriter $permissionSetsWriter) {}

    #[AsEventListener(event: FetchPermissionSetWritersEvent::class)]
    public function __invoke(FetchPermissionSetWritersEvent $event): void
    {
        if ($event->permissionSetRegistryInfo->permissionSet->getSourceInfo()->getPermissionSetProviderName() === YamlPermissionSetsProvider::class) {
            $event->setWriter(clone $this->permissionSetsWriter);
        }
    }
}
