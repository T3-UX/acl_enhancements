<?php

declare(strict_types=1);

/*
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

namespace T3UX\AclEnhancements\Permission\Set;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use T3UX\AclEnhancements\Permission\Provider\PermissionSetsProviderInterface;
use T3UX\AclEnhancements\Permission\Provider\YamlPermissionSetsProvider;
use T3UX\AclEnhancements\Permission\RecordPermissionSetsApplyHandler;
use T3UX\AclEnhancements\Permission\Set\Event\RecordPermissionSetApplyEvent;
use T3UX\AclEnhancements\Permission\Set\Instructions\BackendPermissionInstruction;
use T3UX\AclEnhancements\Permission\Set\Instructions\BackendPermissionInstructionInterface;
use T3UX\AclEnhancements\Permission\Set\Instructions\GetBackendPermissionInstructionInterface;

/**
 * Default implementation for {@see PermmissionSetInterface} created by {@see PermissionSetFactory},
 * for example in {@see YamlPermissionSetsProvider::getSets()}.
 *
 * Implements {@see GetBackendPermissionInstructionInterface} to provide {@see BackendPermissionInstruction}
 * to be handled automatically in {@see RecordPermissionSetsApplyHandler} and event listener implementation
 * of dispatched {@see RecordPermissionSetApplyEvent} in `EXT:workspaces` and `EXT:dashboard`.
 *
 * @internal This is an experimental implementation and might change in TYPO3 v14.
 */
#[Exclude]
readonly class PermissionSet implements PermissionSetInterface, GetBackendPermissionInstructionInterface
{
    /**
     * @param PermissionSetSourceInterface $permissionSetSource
     * @param non-empty-string $label
     * @param array<string, mixed> $instructions
     */
    public function __construct(
        private PermissionSetSourceInterface $permissionSetSource,
        private string $label,
        private array $instructions,
        private PermissionSetState $permissionSetState,
        private BackendPermissionInstructionInterface $backendPermissionInstruction,
    ) {
        $identifier = $permissionSetSource->getIdentifier();
        if (trim($identifier, ' ') === '') {
            throw new \InvalidArgumentException(
                'Invalid value provided for $identifier, must be a non-empty-string',
                1726420661,
            );
        }
        if (trim($label, ' ') === '') {
            throw new \InvalidArgumentException(
                'Invalid value provided for $label, must be a non-empty-string',
                1726420720,
            );
        }
    }

    /**
     * Reconstitute an instance of {@see self}. Used to reconstitute from code cache.
     */
    public static function __set_state(array $data): self
    {
        return new self(...$data);
    }

    /**
     * Permission preset identifier.
     *
     * {@see PermissionSetsProviderInterface::getSets()} are responsible to generated identifiers which are most likely
     * not conflicting, for example '{provider}/{vendor}/{extension}/{filename}' in case of the TYPO3 Default
     * yaml file provider within {@see YamlPermissionSetsProvider::normalizeIdentifier()}.
     *
     * @return non-empty-string
     */
    public function getIdentifier(): string
    {
        return trim($this->permissionSetSource->getIdentifier(), ' ');
    }

    /**
     * Permission preset label.
     *
     * Either plain value or a translation file string, for example:
     *
     *  LLL:EXT:extension-key/Resources/Private/Language/locallang_permissions.xlf:instance_advanced_editor_preset
     *
     * @return non-empty-string
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * Source context data used by {@see PermissionSetFactoryInterface} to create this object.
     *
     * @return PermissionSetSourceInterface
     */
    public function getSourceInfo(): PermissionSetSourceInterface
    {
        return $this->permissionSetSource;
    }

    /**
     * Determines the state of the permission set
     */
    public function getState(): PermissionSetState
    {
        return $this->permissionSetState;
    }

    /**
     * Permission preset raw instructions.
     *
     * @return array<int|string, mixed>
     */
    public function getInstructions(): array
    {
        return $this->instructions;
    }

    public function getBackendPermissionInstruction(): BackendPermissionInstructionInterface
    {
        return $this->backendPermissionInstruction;
    }

    /**
     * Return table fields grouped by table the permission set is suitable.
     *
     * @return array<non-empty-string, string[]>
     */
    public function getSuitableTablesAndTableFields(): array
    {
        return [
            'be_groups' => [
                'TSconfig',
                'allowed_languages',
                'availableWidgets',
                'category_perms',
                'custom_options',
                'db_mountpoints',
                'explicit_allowdeny',
                'file_mountpoints',
                'file_permissions',
                'groupMods',
                'mfa_providers',
                'non_exclude_fields',
                'pagetypes_select',
                'tables_modify',
                'tables_select',
                'workspace_perms',
            ],
        ];
    }
}
