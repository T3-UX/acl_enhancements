<?php

declare(strict_types=1);

/*
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

namespace T3UX\AclEnhancements\Permission\Set;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use T3UX\AclEnhancements\Permission\RecordPermissionSetsApplyHandler;
use T3UX\AclEnhancements\Permission\Set\Instructions\GetBackendPermissionInstructionInterface;

/**
 * Describes the minimal surface for permission set class implementations,
 * for example the default {@see PermissionSet}. Implementation can be
 * enriched with custom interfaces to control special handling later, like
 * {@see GetBackendPermissionInstructionInterface} respected in default
 * {@see RecordPermissionSetsApplyHandler} implementation.
 *
 * @internal This is an experimental implementation and might change in TYPO3 v14.
 */
#[Exclude]
interface PermissionSetInterface
{
    /**
     * Reconstitute an instance of {@see self}. Used to reconstitute from code cache.
     */
    public static function __set_state(array $data): PermissionSetInterface;

    /**
     * Permission preset identifier.
     *
     * {@see PermissionSetsProviderInterface::getSets()} are responsible to generated identifiers which are most likely
     * not conflicting, for example '{provider}/{vendor}/{extension}/{filename}' in case of the TYPO3 Default yaml file
     * provider within {@see YamlPermissionSetsProvider::normalizeIdentifier()}.
     *
     * @return non-empty-string
     */
    public function getIdentifier(): string;

    /**
     * Permission preset label.
     *
     * Either plain value or a translation file string, for example:
     *
     *  LLL:EXT:extension-key/Resources/Private/Language/locallang_permissions.xlf:instance_advanced_editor_preset
     *
     * @return non-empty-string
     */
    public function getLabel(): string;

    /**
     * Source context data used by {@see PermissionSetFactoryInterface} to create this object.
     *
     * @return PermissionSetSourceInterface
     */
    public function getSourceInfo(): PermissionSetSourceInterface;

    /**
     * Determines the state of the permission set
     */
    public function getState(): PermissionSetState;

    /**
     * Permission preset raw instructions.
     *
     * @return array<int|string, mixed>
     */
    public function getInstructions(): array;

    /**
     * Return table fields grouped by table the permission set is suitable.
     *
     * @return array<non-empty-string, string[]>
     */
    public function getSuitableTablesAndTableFields(): array;
}
