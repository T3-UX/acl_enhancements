<?php

declare(strict_types=1);

/*
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

namespace T3UX\AclEnhancements\Permission\Set;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Information object describing the source of permission set data, used to create a {@see PermissionSetInterface}
 * instance by {@see PermissionSetFactoryInterface}, for example default {@see PermissionSet} by provided default
 * {@see PermissionSetFactory}.
 *
 * @internal This is an experimental implementation and might change in TYPO3 v14.
 */
#[Exclude]
readonly class PermissionSetSource implements PermissionSetSourceInterface
{
    /**
     * @param non-empty-string $identifier
     * @param non-empty-string $packageName
     * @param non-empty-string $permissionSetProviderName
     * @param array<int|string, mixed> $data
     * @param array<int|string, mixed> $additionalContextData
     */
    public function __construct(
        private PermissionSetState $state,
        private string $identifier,
        private string $packageName,
        private string $permissionSetProviderName,
        private array $data,
        private array $additionalContextData,
    ) {}

    /**
     * Reconstitute an instance of {@see self}. Used to reconstitute from code cache.
     */
    public static function __set_state(array $state): self
    {
        return new self(...$state);
    }

    /**
     * @return non-empty-string
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * @return non-empty-string
     */
    public function getPackageName(): string
    {
        return $this->packageName;
    }

    /**
     * @return non-empty-string
     */
    public function getPermissionSetProviderName(): string
    {
        return $this->permissionSetProviderName;
    }

    /**
     * Loaded raw data from source.
     *
     * @return array<int|string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

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
    public function getAdditionalContextData(): array
    {
        return $this->additionalContextData;
    }

    /**
     * Initial source state.
     */
    public function getState(): PermissionSetState
    {
        return $this->state;
    }
}
