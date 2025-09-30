<?php

declare(strict_types=1);

/*
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

namespace T3UX\AclEnhancements\Permission\Set;

use T3UX\AclEnhancements\Permission\Provider\PermissionSetsProviderInterface;
use T3UX\AclEnhancements\Permission\Provider\YamlPermissionSetsProvider;

/**
 * Information object describing the source of permission set data, used to create a {@see PermissionSetInterface}
 * instance by {@see PermissionSetFactoryInterface}, for example default {@see PermissionSet} by provided default
 * {@see PermissionSetFactory}.
 *
 * Default implementation {@see PermissionSetSource} is used in default {@see YamlPermissionSetsProvider}.
 *
 * @internal This is an experimental implementation and might change in TYPO3 v14.
 */
interface PermissionSetSourceInterface
{
    /**
     * Reconstitute an instance of {@see self}. Used to reconstitute from code cache.
     */
    public static function __set_state(array $state): self;

    /**
     * Initial source state.
     */
    public function getState(): PermissionSetState;

    /**
     * Source identifier, for example basename of a file like in {@see YamlPermissionSetsProvider}.
     *
     * @return non-empty-string
     */
    public function getIdentifier(): string;

    /**
     * Package name, for example extension composer package name (`vendor/package-name`) or
     * `system` in case of global instance folder provided by {@see YamlPermissionSetsProvider}.
     *
     * @return non-empty-string
     */
    public function getPackageName(): string;

    /**
     * Provider class name, for example {@see YamlPermissionSetsProvider::class}.
     *
     * @return non-empty-string
     */
    public function getPermissionSetProviderName(): string;

    /**
     * Loaded raw data from source.
     *
     * @return array<int|string, mixed>
     */
    public function getData(): array;

    /**
     * Additional context data.
     *
     * Can be filled with additional context data to when passed to {@see PermissionSetFactoryInterface::create()}
     * to create a {@see PermissionSetInterface} and retrieved from {@see PermissionSetsProviderInterface::getSets()}.
     *
     * Note that added objects set as additional context data **must** implement magic method `__set_state()`
     * {@see https://www.php.net/manual/en/language.oop5.magic.php#object.set-state}, otherwise writing to
     * cache will fail.
     *
     * @return array<string, mixed>
     */
    public function getAdditionalContextData(): array;
}
