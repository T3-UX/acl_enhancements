<?php

declare(strict_types=1);

/*
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

namespace T3UX\AclEnhancements\Permission\Set;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use T3UX\AclEnhancements\Permission\Provider\YamlPermissionSetsProvider;
use T3UX\AclEnhancements\Permission\Set\Instructions\BackendPermissionInstructionFactoryInterface;

/**
 * Default implementation of {@see PermissionSetFactoryInterface} to create the default
 * implementation {@see PermissionSet} for {@see PermissionSetInterface}.
 *
 * Used within {@see YamlPermissionSetsProvider::getSets()}.
 *
 * @internal This is an experimental implementation and might change in TYPO3 v14.
 */
#[AsAlias(id: PermissionSetFactoryInterface::class)]
readonly class PermissionSetFactory implements PermissionSetFactoryInterface
{
    public function __construct(
        private BackendPermissionInstructionFactoryInterface $backendPermissionInstructionFactory,
    ) {}

    /**
     * Create {@see PermissionSetInterface} from {@see PermissionSetSourceInterface},
     * in this case default implementation {@see PermissionSet}.
     */
    public function create(PermissionSetSourceInterface $source): PermissionSetInterface
    {
        return new PermissionSet(
            permissionSetSource: $source,
            label: $this->determineLabel($source),
            instructions: $source->getData(),
            permissionSetState: ($source->getData() === [] && $source->getState()->isValid())
                ? PermissionSetState::NO_DATA
                : $source->getState(),
            backendPermissionInstruction: $this->backendPermissionInstructionFactory->create($source),
        );
    }

    /**
     * Determine label from loaded data, using {@see PermissionSetSourceInterface::getIdentifier()} as fallback.
     */
    private function determineLabel(PermissionSetSourceInterface $source): string
    {
        $label = $source->getData()['label'] ?? null;
        return is_string($label) && $label !== '' ? $label : $source->getIdentifier();
    }
}
