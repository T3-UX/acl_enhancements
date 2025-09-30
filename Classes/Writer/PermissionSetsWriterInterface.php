<?php

/*
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

namespace T3UX\AclEnhancements\Writer;

use T3UX\AclEnhancements\Permission\PermissionSetRegistryInfo;

interface PermissionSetsWriterInterface
{
    /**
     * @param array<int|string, mixed>|null $row
     */
    public function saveToInstance(?array $row = [], string $newFilename = '', ?PermissionSetRegistryInfo $permissionSet = null): PermissionSetRegistryInfo;

    /**
     * @param array<int|string, mixed> $setContents
     */
    public function write(array $setContents, string $filename, bool $overwriteFile = false): PermissionSetRegistryInfo;

    /**
     * @param array<int|string, mixed> $data
     */
    public function getPermissionSetContent(array $data): string;

    public function delete(PermissionSetRegistryInfo $permissionSetRegistryInfo): void;

    public function doesFilenameExist(string $filename): bool;

    public function rename(PermissionSetRegistryInfo $permissionSetRegistryInfo, string $newFilename): void;

    public function isEditable(PermissionSetRegistryInfo $permissionSetRegistryInfo): bool;

    public function isReadable(PermissionSetRegistryInfo $permissionSetRegistryInfo): bool;

    public function isRemovable(PermissionSetRegistryInfo $permissionSetRegistryInfo): bool;
}
