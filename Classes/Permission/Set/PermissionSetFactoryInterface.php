<?php

declare(strict_types=1);

/*
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

namespace T3UX\AclEnhancements\Permission\Set;

/**
 * Interface defining expected methods for custom permission set factory implementations,
 * can be used to implement custom factories using decorated Symfony Dependency Injection
 * service.
 *
 * {@link https://symfony.com/doc/current/service_container/service_decoration.html}
 *
 * @internal This is an experimental implementation and might change in TYPO3 v14.
 */
interface PermissionSetFactoryInterface
{
    /**
     * Create {@see PermissionSetInterface} from {@see PermissionSetSourceInterface}.
     */
    public function create(PermissionSetSourceInterface $source): PermissionSetInterface;
}
