<?php

declare(strict_types=1);

/*
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

namespace T3UX\AclEnhancements\Xclass;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
class BackendUserController extends \TYPO3\CMS\Beuser\Controller\BackendUserController
{
    protected function addMainMenu(string $currentAction): void
    {
        parent::addMainMenu($currentAction);

        try {
            $menus = $this->moduleTemplate->getDocHeaderComponent()->getMenuRegistry()->getMenus();

            if (!isset($menus['BackendUserModuleMenu'])) {
                return;
            }

            $menu = $menus['BackendUserModuleMenu'];

            $menu->addMenuItem(
                $menu->makeMenuItem()
                    ->setTitle('Permission sets')
                    ->setHref(
                        $this->uriBuilder->uriFor(
                            'index',
                            [],
                            'PresetsModule',
                            'acl_enhancement',
                            'acl_enhancements_module'
                        )
                    )
                    ->setActive(false)
            );

            $this->moduleTemplate->getDocHeaderComponent()->getMenuRegistry()->addMenu($menu);
        } catch (\Throwable) {
        }
    }
}
