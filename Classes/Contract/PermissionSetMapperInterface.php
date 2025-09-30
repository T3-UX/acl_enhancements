<?php

declare(strict_types=1);

/*
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

namespace T3UX\AclEnhancements\Contract;

interface PermissionSetMapperInterface
{
    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    public function fromRow(array $row): array;
}
