<?php

declare(strict_types=1);

/*
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

namespace T3UX\AclEnhancements\Permission\Set\Instructions;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Backend permission instruction for {@see RecordPermissionSetsApplyHandler}.
 *
 * @internal This is an experimental implementation and might change in TYPO3 v14.
 */
#[Exclude]
readonly class BackendPermissionInstruction implements BackendPermissionInstructionInterface
{
    /**
     * @param string[] $categories
     * @param string[] $customOptions
     * @param string[] $filePermissions
     * @param int[] $fileMounts
     * @param string[] $languages
     * @param string[] $mfaProviders
     * @param array<non-empty-string|int, bool|string> $modules
     * @param array<non-empty-string, array{permissions?: string[], fields?: string[], selectFieldItems?: array<int,string>|array<string, array<int, string|int>>}> $resources
     * @param array<int|string, mixed> $settings
     * @param array<int, int|string> $sites
     * @param string[] $widgets
     * @param bool|null $workspaceLiveEditing
     */
    public function __construct(
        public array $categories = [],
        public array $customOptions = [],
        public array $filePermissions = [],
        public array $fileMounts = [],
        public array $languages = [],
        public array $mfaProviders = [],
        public array $modules = [],
        public array $resources = [],
        public array $settings = [],
        public array $sites = [],
        public array $widgets = [],
        public ?bool $workspaceLiveEditing = null,
    ) {}

    public static function __set_state(array $data): self
    {
        return new self(...$data);
    }
}
