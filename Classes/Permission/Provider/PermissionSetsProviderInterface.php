<?php

declare(strict_types=1);

/*
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

namespace T3UX\AclEnhancements\Permission\Provider;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use T3UX\AclEnhancements\Permission\Set\PermissionSetInterface;

/**
 * Define the public facing API required to implement permission presets providers to
 * load permissions based on other formats, for example `ini-files`, `php-files` or a
 * REST-API or just from other places then the default {@see YamlPermissionSetsProvider}.
 *
 * @internal This is an experimental implementation and might change in TYPO3 v14.
 */
#[AutoconfigureTag(name: 'permission-sets.provider')]
interface PermissionSetsProviderInterface
{
    /**
     * @return array<non-empty-string, PermissionSetInterface>
     */
    public function getSets(): array;
}
