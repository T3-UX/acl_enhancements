<?php

declare(strict_types=1);

/*
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

namespace T3UX\AclEnhancements\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Backend\Routing\UriBuilder as BackendUriBuilder;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\Components\ModifyButtonBarEvent;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;

#[Autoconfigure(public: true)]
class DocheaderExportButton
{
    public function __construct(
        protected UriBuilder $uriBuilder,
        protected BackendUriBuilder $backendUriBuilder,
        protected IconFactory $iconFactory,
    ) {}

    public function __invoke(ModifyButtonBarEvent $event): void
    {
        $request = $this->getRequest();
        if (!$request instanceof ServerRequest) {
            return;
        }

        if ($this->isBeGroupsEdit($request)) {
            $this->addSingleExportButtons($event, $request);
        }
    }

    private function addSingleExportButtons(ModifyButtonBarEvent $event, ServerRequest $request): void
    {
        $beGroupUid = $this->getEditedBeGroupUid($request);

        if ($beGroupUid === 0) {
            return;
        }

        $normalizedParams = $request->getAttribute('normalizedParams');
        $returnUrl = $normalizedParams?->getRequestUri();

        $this->initUriBuilder($request);

        $buttons = $event->getButtons();

        $buttons[ButtonBar::BUTTON_POSITION_LEFT][][] = $event->getButtonBar()
            ->makeLinkButton()
            ->setTitle($this->translate('LLL:EXT:acl_enhancements/Resources/Private/Language/locallang_tca.xlf:permissionSets.openInACLPresetsCreate'))
            ->setIcon($this->iconFactory->getIcon('actions-chevron-right', IconSize::SMALL))
            ->setShowLabelText(true)
            ->setHref(
                $this->uriBuilder->uriFor(
                    'create',
                    [
                        'uid' => $beGroupUid,
                    ],
                    'PresetsModule',
                    'acl_enhancements',
                    'acl_enhancements_module'
                )
            );

        ksort($buttons[ButtonBar::BUTTON_POSITION_LEFT]);
        $event->setButtons($buttons);
    }

    private function getRequest(): ?ServerRequest
    {
        $req = $GLOBALS['TYPO3_REQUEST'] ?? null;
        return $req instanceof ServerRequest ? $req : null;
    }

    private function isBeGroupsEdit(ServerRequest $request): bool
    {
        $q = $request->getQueryParams();
        $p = $request->getParsedBody();
        $edit = $p['edit'] ?? $q['edit'] ?? null;

        if (!is_array($edit) || !isset($edit['be_groups']) || !is_array($edit['be_groups'])) {
            return false;
        }
        return in_array('edit', $edit['be_groups'], true);
    }

    private function getEditedBeGroupUid(ServerRequest $request): int
    {
        $queryParams = $request->getQueryParams();
        $parsedBody = $request->getParsedBody();
        $edit = $parsedBody['edit'] ?? $queryParams['edit'] ?? null;

        if (!is_array($edit) || !isset($edit['be_groups']) || !is_array($edit['be_groups'])) {
            return 0;
        }
        foreach ($edit['be_groups'] as $uid => $mode) {
            if ($mode === 'edit' && is_numeric($uid)) {
                return (int)$uid;
            }
        }
        return 0;
    }

    private function initUriBuilder(ServerRequest $request): void
    {
        $fakeRequest = clone $request;
        $fakeRequest = $fakeRequest->withAttribute('extbase', new ExtbaseRequestParameters());
        $fakeExtbaseRequest = new Request($fakeRequest);
        $this->uriBuilder->setRequest($fakeExtbaseRequest);
    }

    private function translate(string $key): string
    {
        return $GLOBALS['LANG']->sL($key);
    }
}
