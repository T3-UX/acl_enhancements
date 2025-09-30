<?php

declare(strict_types=1);

/*
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

namespace T3UX\AclEnhancements\Event;

use T3UX\AclEnhancements\Permission\PermissionSetRegistryInfo;
use T3UX\AclEnhancements\Writer\PermissionSetsWriterInterface;

final class FetchPermissionSetWritersEvent
{
    protected ?PermissionSetsWriterInterface $writer = null;

    public function __construct(
        public PermissionSetRegistryInfo $permissionSetRegistryInfo,
    ) {}

    public function getWriter(): ?PermissionSetsWriterInterface
    {
        return $this->writer;
    }

    public function setWriter(?PermissionSetsWriterInterface $writer): void
    {
        $this->writer = $writer;
    }
}
