<?php

declare(strict_types=1);

/*
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

namespace T3UX\AclEnhancements\Controller;

use Psr\Http\Message\ResponseInterface;
use T3UX\AclEnhancements\Contract\PermissionSetMapperInterface;
use T3UX\AclEnhancements\Event\PermissionSetChangedEvent;
use T3UX\AclEnhancements\Factory\PermissionSetWriterFactory;
use T3UX\AclEnhancements\Permission\PermissionSetRegistryInfo;
use T3UX\AclEnhancements\Permission\PermissionSetsRegistry;
use T3UX\AclEnhancements\Utility\YamlFileUtility;
use T3UX\AclEnhancements\Writer\YamlPermissionSetsWriter;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Form\FormDataCompiler;
use TYPO3\CMS\Backend\Form\FormDataGroup\TcaDatabaseRecord;
use TYPO3\CMS\Backend\Form\FormResultCompiler;
use TYPO3\CMS\Backend\Form\NodeFactory;
use TYPO3\CMS\Backend\Routing\UriBuilder as BackendUriBuilder;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\Features;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\AllowedMethodsTrait;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

#[AsController]
class PresetsModuleController extends ActionController
{
    use AllowedMethodsTrait;

    public function __construct(
        protected readonly ModuleTemplateFactory $moduleTemplateFactory,
        protected readonly IconFactory $iconFactory,
        protected readonly BackendUriBuilder $backendUriBuilder,
        protected readonly FormDataCompiler $formDataCompiler,
        protected readonly PermissionSetsRegistry $permissionSetsRegistry,
        protected readonly NodeFactory $nodeFactory,
        protected readonly FormResultCompiler $formResultCompiler,
        protected readonly FlashMessageService $flashMessageService,
        protected readonly PermissionSetWriterFactory $writerFactory,
        protected readonly YamlPermissionSetsWriter $defaultWriter,
        protected readonly Features $features,
        protected readonly PermissionSetMapperInterface $permissionSetMapper,
        protected readonly ConnectionPool $connectionPool,
    ) {}

    protected function initializeAction(): void
    {
        $this->eventDispatcher->dispatch(new PermissionSetChangedEvent(''));
    }

    public function indexAction(): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setTitle('ACL Presets – List of presets');
        $permissionSets = $this->getPermissionSetsForListing();

        $moduleTemplate->assignMultiple([
            'permissionSets' => $permissionSets,
            'mergeAsRevisions' => $this->features->isFeatureEnabled('acl.revisions'),
        ]);

        $this->addModuleDocHeaderButtons($moduleTemplate);
        $this->addModuleMenu($moduleTemplate);

        return $moduleTemplate->renderResponse('AclPresets/Index');
    }

    /**
     * @param array{be_groups: array<string,array<mixed>>}|array<mixed> $data
     */
    public function savePresetAction(array $data, string $presetIdentifier = ''): ResponseInterface
    {
        if ($presetIdentifier === '' || !$this->permissionSetsRegistry->has($presetIdentifier)) {
            $this->addFlashMessageToQueue('Failure', 'Oops an error occurred during processing of the save request.', ContextualFeedbackSeverity::ERROR);
            return $this->redirect('index');
        }

        /** @var PermissionSetRegistryInfo $preset */
        $preset = $this->permissionSetsRegistry->get($presetIdentifier);

        $writer = $this->writerFactory->getWriterForPermissionSet($preset);

        if ($writer === null) {
            $this->addFlashMessageToQueue('Failure', 'Writer for this preset is unknown.', ContextualFeedbackSeverity::ERROR);

            return new RedirectResponse(
                $this->uriBuilder->uriFor(
                    'edit',
                    [
                        'presetIdentifier' => $presetIdentifier,
                    ]
                )
            );
        }

        try {
            $row = $data['be_groups'] ?? [];
            $row = array_pop($row);

            $newIdentifier = $row['identifier'];
            unset($row['identifier']);

            $row = $this->permissionSetMapper->fromRow($row);

            $preset = $writer->saveToInstance($row, $newIdentifier, $preset);
            $this->addFlashMessageToQueue('Success', 'Save completed', ContextualFeedbackSeverity::OK);
        } catch (\Throwable $e) {
            $this->addFlashMessageToQueue('Failure', $e->getMessage(), ContextualFeedbackSeverity::ERROR);
        }

        return new RedirectResponse(
            $this->uriBuilder->uriFor(
                'edit',
                [
                    'presetIdentifier' => $preset->identifier,
                ]
            )
        );
    }

    public function duplicateAction(string $presetIdentifier = ''): ResponseInterface
    {
        if ($presetIdentifier === '' || !$this->permissionSetsRegistry->has($presetIdentifier)) {
            $this->addFlashMessageToQueue('Failure', 'Can\'t find preset.', ContextualFeedbackSeverity::ERROR);
            return $this->redirect('index');
        }

        $permissionSet = $this->permissionSetsRegistry->get($presetIdentifier);

        $writer = $this->writerFactory->getWriterForPermissionSet($permissionSet);

        if ($writer === null) {
            $this->addFlashMessageToQueue('Failure', 'Can\'t find preset.', ContextualFeedbackSeverity::ERROR);

            return new RedirectResponse(
                $this->uriBuilder->uriFor(
                    'index',
                )
            );
        }

        $permissionSet = $this->permissionSetsRegistry->get($presetIdentifier);

        $localIdentifier = $this->getFilenameCandidate(
            YamlFileUtility::translateIdentifierToFilename($permissionSet->identifier)
        );

        $preset = $writer->saveToInstance(
            $permissionSet->permissionSet->getSourceInfo()->getData(),
            newFilename: $localIdentifier,
            permissionSet: $permissionSet
        );

        $this->addFlashMessageToQueue('Successfully duplicated', '', ContextualFeedbackSeverity::INFO);

        return $this->redirect(
            'edit',
            arguments: [
                'presetIdentifier' => $preset->identifier,
            ]
        );
    }

    public function deleteAction(string $presetIdentifier = ''): ResponseInterface
    {
        if ($presetIdentifier === '' || !$this->permissionSetsRegistry->has($presetIdentifier)) {
            $this->addFlashMessageToQueue('Failure', 'Can\'t find preset.', ContextualFeedbackSeverity::ERROR);
            return $this->redirect('index');
        }

        $permissionSet = $this->permissionSetsRegistry->get($presetIdentifier);

        $writer = $this->writerFactory->getWriterForPermissionSet($permissionSet);
        if ($writer === null) {
            $this->addFlashMessageToQueue('Failure', 'Unknown writer.', ContextualFeedbackSeverity::ERROR);
            return $this->redirect('index');
        }

        if (!$writer->isRemovable($permissionSet)) {
            $this->addFlashMessageToQueue('Failure', 'Cannot remove.', ContextualFeedbackSeverity::ERROR);
            return $this->redirect('index');
        }

        $writer->delete($permissionSet);

        return $this->redirect('index');
    }

    public function editAction(int $uid = 0, string $presetIdentifier = '', string $filepath = ''): ResponseInterface
    {
        if ($presetIdentifier === '' || !$this->permissionSetsRegistry->has($presetIdentifier)) {
            $presetIdentifier = $this->findIdentifierFromAbsoluteFilename($filepath);

            if ($presetIdentifier === '') {
                $this->addFlashMessageToQueue('Failure', 'Can\'t find preset.', ContextualFeedbackSeverity::ERROR);
                return $this->redirect('index');
            }
        }
        $preset = $this->permissionSetsRegistry->get($presetIdentifier);

        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setTitle('ACL Presets – Create from scratch (be_groups)');

        if ($uid > 0) {
            $groupTitle = BackendUtility::getRecord('be_groups', $uid, 'title')['title'] ?? null;
            if ($groupTitle !== null) {
                $moduleTemplate->assign('subtitle', $groupTitle . '[' . $uid . ']');
            } else {
                $this->addFlashMessageToQueue('Failure', 'Backend usergroup with uid ' . $uid . ' doesn\'t exist.', ContextualFeedbackSeverity::ERROR);
                return $this->redirect('index');
            }
        } else {
            $moduleTemplate->assign('subtitle', $presetIdentifier);
        }

        $this->addDocHeaderButtonsForDetailView($moduleTemplate, $uid, $preset->identifier);

        $moduleTemplate->assignMultiple(
            [
                'content' => $this->renderBackendGroupPermissionPresetTca($uid, $presetIdentifier),
                'title' => 'Permission set edit',
            ]
        );

        return $moduleTemplate->renderResponse('AclPresets/Edit');
    }

    /**
     * @param array<mixed> $data
     */
    public function createAction(int $uid = 0, array $data = []): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setTitle('ACL Presets – Create from scratch (be_groups)');

        if ($data !== []) {
            $row = $data['be_groups'] ?? [];
            $row = array_pop($row);

            $filename = $row['identifier'];
            unset($row['identifier']);

            $row = $this->permissionSetMapper->fromRow($row);

            $permissionSet = $this->defaultWriter->saveToInstance($row, $filename);

            return $this->redirect('edit', arguments: ['presetIdentifier' => $permissionSet->identifier]);
        }

        if ($uid > 0) {
            $row = BackendUtility::getRecord('be_groups', $uid, '*');

            if (($row['title'] ?? '') !== '') {
                $moduleTemplate->assign('subtitle', 'Based on group: ' . $row['title'] . '[' . $uid . ']');
            } else {
                $this->addFlashMessageToQueue('Failure', 'Backend usergroup with uid ' . $uid . ' doesn\'t exist.', ContextualFeedbackSeverity::ERROR);
                return $this->redirect('index');
            }
        }

        $this->addDocHeaderButtonsForDetailView($moduleTemplate, $uid);

        $moduleTemplate->assignMultiple(
            [
                'content' => $this->renderBackendGroupPermissionPresetTca(
                    $uid,
                    actionName: 'createAction'
                ),
                'title' => 'Create new preset',
            ]
        );

        return $moduleTemplate->renderResponse('AclPresets/Create');
    }

    public function downloadAction(string $presetIdentifier = ''): ResponseInterface
    {
        try {
            if ($presetIdentifier === '' || !$this->permissionSetsRegistry->has($presetIdentifier)) {
                return $this->redirect('index');
            }

            $set = $this->permissionSetsRegistry->get($presetIdentifier);

            $writer = $this->writerFactory->getWriterForPermissionSet($set);
            if ($writer === null) {
                $this->addFlashMessageToQueue('Failure', 'Download failed.', ContextualFeedbackSeverity::ERROR);
                return $this->redirect('index');
            }

            if ($writer->isReadable($set)) {
                return $this->generateDownloadResponse(
                    $writer->getPermissionSetContent($set->permissionSet->getSourceInfo()->getData()),
                    $set->permissionSet->getSourceInfo()->getAdditionalContextData()['relativeFileName'],
                    $set->permissionSet->getSourceInfo()->getAdditionalContextData()['absoluteFileName'],
                );
            }
        } catch (\Throwable $e) {
            $this->addFlashMessageToQueue('Failure', 'Download failed.', ContextualFeedbackSeverity::ERROR);
            return $this->redirect('index');
        }

        $this->addFlashMessageToQueue('Failure', 'Unknown error.', ContextualFeedbackSeverity::ERROR);
        return $this->redirect('index');
    }

    public function viewAction(string $presetIdentifier = ''): ResponseInterface
    {
        if ($presetIdentifier === '' || !$this->permissionSetsRegistry->has($presetIdentifier)) {
            return $this->redirect('index');
        }

        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setTitle('ACL Presets – Permission set preview');

        $buttonBar = $moduleTemplate->getDocHeaderComponent()->getButtonBar();

        $buttonBar->addButton(
            $buttonBar->makeLinkButton()
                ->setTitle((string)LocalizationUtility::translate('LLL:EXT:acl_enhancements/Resources/Private/Language/locallang_tca.xlf:permissionSets.goBack'))
                ->setShowLabelText(true)
                ->setIcon($this->iconFactory->getIcon('actions-chevron-left', IconSize::SMALL))
                ->setHref($this->uriBuilder->uriFor('index')),
            ButtonBar::BUTTON_POSITION_LEFT,
            0
        );

        $moduleTemplate->assignMultiple(
            [
                'content' => $this->renderBackendGroupPermissionPresetTca(presetIdentifier: $presetIdentifier, actionName: 'viewAction', readOnly: true),
                'title' => 'Permission set preview',
            ]
        );

        return $moduleTemplate->renderResponse('AclPresets/View');
    }

    protected function renderBackendGroupPermissionPresetTca(
        int $uid = 0,
        string $presetIdentifier = '',
        string $returnUrl = '',
        string $actionName = 'savePreset',
        bool $readOnly = false
    ): string {
        $items = [];
        $formDataCompilerInput = [
            'request' => $this->request,
            'tableName' => 'be_groups',
        ];

        if ($uid <= 0) {
            $formDataCompilerInput['command'] = 'new';
        } else {
            $formDataCompilerInput['command'] = 'edit';
            $formDataCompilerInput['vanillaUid'] = $uid;
        }

        $tcaDatabaseRecord = GeneralUtility::makeInstance(TcaDatabaseRecord::class);
        $formData = $this->formDataCompiler->compile($formDataCompilerInput, $tcaDatabaseRecord);

        if (($formData['databaseRow']['tables_modify']['modify'] ?? null) !== null) {
            $formData['databaseRow']['tables_select'] = $formData['databaseRow']['tables_modify']['select'];
            $formData['databaseRow']['tables_modify'] = $formData['databaseRow']['tables_modify']['modify'];
        }

        $formData['databaseRow']['permission_sets'] = $presetIdentifier;
        $formData['presetIdentifier'] = $presetIdentifier; // hacky way to apply current permission sets

        $formData['databaseRow']['db_mountpoints'] = implode(',', array_column($formData['databaseRow']['db_mountpoints'] ?? [], 'uid'));

        $formData['processedTca']['types']['0'] = [
            'showitem' => '
            --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general,
                label,
                identifier,
                usedIn,
            --div--;LLL:EXT:core/Resources/Private/Language/locallang_tca.xlf:be_groups.tabs.record_permissions,
                pagetypes_select, tables_modify, non_exclude_fields, explicit_allowdeny, allowed_languages,
            --div--;LLL:EXT:core/Resources/Private/Language/locallang_tca.xlf:be_groups.tabs.module_permissions,
                groupMods, availableWidgets, custom_options, mfa_providers, workspace_perms,
            --div--;LLL:EXT:core/Resources/Private/Language/locallang_tca.xlf:be_groups.tabs.mounts_and_workspaces,
                db_mountpoints, file_mountpoints, file_permissions, category_perms,
            --div--;LLL:EXT:core/Resources/Private/Language/locallang_tca.xlf:be_groups.tabs.options,
                TSconfig,
            --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:notes,
                description,
            --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:extended,
        ',
        ];

        $formData['processedTca']['columns']['identifier'] = [
            'label' => 'Filename (change at your own risk!)',
            'config' => [
                'type' => 'input',
                'eval' => 'trim',
                'default' => YamlFileUtility::translateIdentifierToFilename($presetIdentifier),
            ],
        ];

        $backendGroups = $this->findBeGroupsUsingPreset($presetIdentifier, ['uid', 'title']);

        foreach ($backendGroups as $group) {
            $items[] = ['label' => $group['title'], 'value' => $group['uid']];
        }

        $formData['processedTca']['columns']['usedIn'] = [
            'label' => 'Used by following backend user groups:',
            'config' => [
                'type' => 'user',
                'renderType' => 'presetUsedInField',
                'items' => $items,
                'default' => YamlFileUtility::translateIdentifierToFilename($presetIdentifier),
            ],
        ];

        $formData['processedTca']['columns']['label'] = $formData['processedTca']['columns']['title'];

        if ($presetIdentifier !== '') {
            if (!$this->permissionSetsRegistry->has($presetIdentifier)) {
                throw new \Exception('Permission set not found');
            }
            $preset = $this->permissionSetsRegistry->get($presetIdentifier);

            $title = $preset->permissionSet->getSourceInfo()->getData()['title'] ?? null;
            if (trim($title ?? '') === '') {
                $title = $preset->permissionSet->getLabel();
            }

            $formData['databaseRow']['label'] = $title;
        } elseif ($uid > 0) {
            $title = BackendUtility::getRecord('be_groups', $uid, 'title')['title'] ?? null;

            $formData['databaseRow']['label'] = $title;

            $localIdentifier = $this->getFilenameCandidate($title);

            $formData['databaseRow']['identifier'] = $localIdentifier;

            if ($title === null) {
                throw new \Exception('Backend usergroup not found');
            }
        }

        if ($readOnly) {
            foreach ($formData['processedTca']['columns'] as $key => $column) {
                $formData['processedTca']['columns'][$key]['config']['readOnly'] = true;
            }
        }

        $formData = $tcaDatabaseRecord->compile($formData);

        $formData['renderType'] = 'outerWrapContainer';

        $formResult = $this->nodeFactory->create($formData)->render();

        $this->formResultCompiler->mergeResult($formResult);

        $formResult['html'] = $this->compileForm($formResult['html'], $presetIdentifier, $returnUrl, $actionName);
        return $formResult['html'] . $this->formResultCompiler->printNeededJSFunctions();
    }

    protected function compileForm(string $editForm, string $presetIdentifier, string $returnUrl = '', string $actionName = 'savePreset'): string
    {
        $formUrl = $this->uriBuilder->uriFor(
            $actionName,
            [],
            'PresetsModule',
            'acl_enhancement',
            'acl_enhancements_module'
        );

        if ($returnUrl !== '') {
            $editForm .= '<input type="hidden" name="returnUrl" value="' . $returnUrl . '/>';
        }

        $formContent = '
            <form
                action="' . htmlspecialchars($formUrl) . '"
                method="post"
                enctype="multipart/form-data"
                name="editform"
                id="PresetsModuleController"
            >
            ' . $editForm . '
            <input type="hidden" name="presetIdentifier" value="' . $presetIdentifier . '" />
            <input type="hidden" name="closeDoc" value="0" />
            <input type="hidden" name="doSave" value="0" />
            <input type="hidden" name="_savedok" value="1" />
            </form>';
        return $formContent;
    }

    /**
     * @return list<array<int|string,mixed>>
     * @throws \TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException
     */
    protected function getPermissionSetsForListing(): array
    {
        $params = [];

        foreach ($this->permissionSetsRegistry as $permissionSet) {
            $tstamp = null;
            $viewUrl = '';
            $editUrl = '';
            $deleteUrl = '';
            $downloadUrl = '';
            $duplicateUrl = '';

            $additionalFileContextData = $permissionSet->permissionSet->getSourceInfo()->getAdditionalContextData();

            $crdate = $additionalFileContextData['crdate'] ?? null;
            $tstamp = $additionalFileContextData['tstamp'] ?? null;

            $writer = $this->writerFactory->getWriterForPermissionSet($permissionSet);

            $isValid = $permissionSet->permissionSet->getState()->isValid();

            if ($writer !== null) {
                if ($writer->isRemovable($permissionSet)) {
                    $deleteUrl = $this->uriBuilder->uriFor(
                        'delete',
                        [
                            'presetIdentifier' => $permissionSet->permissionSet->getIdentifier(),
                            'returnUrl' => $this->request->getUri(),
                        ]
                    );
                }

                if ($writer->isReadable($permissionSet)) {
                    $duplicateUrl = $this->uriBuilder->uriFor(
                        'duplicate',
                        [
                            'presetIdentifier' => $permissionSet->permissionSet->getIdentifier(),
                            'returnUrl' => $this->request->getUri(),
                        ]
                    );
                    $downloadUrl = $this->uriBuilder->uriFor(
                        'download',
                        [
                            'presetIdentifier' => $permissionSet->permissionSet->getIdentifier(),
                            'returnUrl' => $this->request->getUri(),
                        ]
                    );
                    $viewUrl = $this->uriBuilder->uriFor(
                        'view',
                        [
                            'presetIdentifier' => $permissionSet->permissionSet->getIdentifier(),
                            'returnUrl' => $this->request->getUri(),
                        ]
                    );
                }

                if ($isValid && $writer->isEditable($permissionSet)) {
                    $editUrl = $this->uriBuilder->uriFor(
                        'edit',
                        [
                            'presetIdentifier' => $permissionSet->permissionSet->getIdentifier(),
                            'returnUrl' => $this->request->getUri(),
                        ]
                    );
                }
            }

            $params[] = [
                'label' => $permissionSet->permissionSet->getLabel(),
                'package' => $permissionSet->permissionSet->getSourceInfo()->getPackageName(),
                'value' => $permissionSet->identifier,
                'controls' => [
                    'edit' => $editUrl,
                    'delete' => $deleteUrl,
                    'duplicate' => $duplicateUrl,
                    'download' => $downloadUrl,
                    'view' => $viewUrl,
                ],
                'crdate' => $crdate ?? null,
                'tstamp' => $tstamp ?? null,
                'readOnly' => $editUrl === '' && $deleteUrl === '',
                'isValid' => $permissionSet->permissionSet->getState()->isValid(),
                'usedIn' => count($this->findBeGroupsUsingPreset($permissionSet->identifier, ['title'])),
            ];
        }

        $ordered = [];

        if ($this->features->isFeatureEnabled('acl.revisions')) {
            foreach ($params as $row) {
                $ordered[$row['package'] . '-' . $row['label']][] = $row;
            }
        } else {
            foreach ($params as $row) {
                $ordered[$row['value']][] = $row;
            }
        }

        foreach ($ordered as $key => $items) {
            usort($items, static function ($a, $b) {
                $aTime = (int)($a['tstamp'] ?? 0) > 0 ? (int)$a['tstamp'] : 0;
                $bTime = (int)($b['tstamp'] ?? 0) > 0 ? (int)$b['tstamp'] : 0;
                return $bTime <=> $aTime;
            });

            $newestPreset = $items[0];
            unset($items[0]);

            $ordered[$key] = [
                'label' => $newestPreset['label'],
                'newest' => $newestPreset,
                'items' => $items,
                'revisions' => count($items) + 1,
            ];
        }

        usort($ordered, static function ($a, $b) {
            if (!isset($a['newest']['tstamp'], $b['newest']['tstamp'])) {
                return 0;
            }

            $aTstamp = $a['newest']['tstamp'];
            $bTstamp = $b['newest']['tstamp'];

            $aTime = is_numeric($aTstamp) > 0 ? (int)$aTstamp : 0;
            $bTime = is_numeric($bTstamp) > 0 ? (int)$bTstamp : 0;

            return $bTime <=> $aTime;
        });

        return $ordered;
    }

    protected function findIdentifierFromAbsoluteFilename(string $path): string
    {
        foreach ($this->permissionSetsRegistry as $permissionSet) {
            if (!$permissionSet->permissionSet->getState()->isValid()) {
                // only valid permission sets should be shown.
                continue;
            }
            $relativePath = $permissionSet->permissionSet->getSourceInfo()->getAdditionalContextData()['absoluteFileName'] ?? null;

            if ($path === $relativePath) {
                return $permissionSet->permissionSet->getIdentifier();
            }
        }

        return '';
    }

    protected function addFlashMessageToQueue(string $title, string $message, ContextualFeedbackSeverity $severity): void
    {
        $flashMessage = GeneralUtility::makeInstance(
            FlashMessage::class,
            $message,
            $title,
            $severity,
            true
        );
        $queue = $this->flashMessageService->getMessageQueueByIdentifier();
        $queue->addMessage($flashMessage);
    }

    protected function addModuleMenu(ModuleTemplate $view): void
    {
        $menu = $view->getDocHeaderComponent()->getMenuRegistry()->makeMenu();
        $menu->setIdentifier('BackendUserModuleMenu');
        $menu->setLabel(
            (string)LocalizationUtility::translate(
                'LLL:EXT:backend/Resources/Private/Language/locallang.xlf:modulemenu.label',
                'backend',
            )
        );

        $beUserArgs = [[], 'BackendUser', 'beuser', 'backend_user_management'];

        $menu->addMenuItem(
            $menu->makeMenuItem()
                ->setTitle((string)LocalizationUtility::translate('LLL:EXT:beuser/Resources/Private/Language/locallang.xlf:backendUsers', 'beuser'))
                ->setHref($this->uriBuilder->uriFor('list', ...$beUserArgs))
                ->setActive(false)
        );

        $menu->addMenuItem(
            $menu->makeMenuItem()
                ->setTitle((string)LocalizationUtility::translate('LLL:EXT:beuser/Resources/Private/Language/locallang.xlf:backendUserGroupsMenu', 'beuser'))
                ->setHref($this->uriBuilder->uriFor('groups', ...$beUserArgs))
                ->setActive(false)
        );

        $menu->addMenuItem(
            $menu->makeMenuItem()
                ->setTitle((string)LocalizationUtility::translate('LLL:EXT:beuser/Resources/Private/Language/locallang.xlf:onlineUsers', 'beuser'))
                ->setHref($this->uriBuilder->uriFor('online', ...$beUserArgs))
                ->setActive(false)
        );

        $menu->addMenuItem(
            $menu->makeMenuItem()
                ->setTitle((string)LocalizationUtility::translate('LLL:EXT:beuser/Resources/Private/Language/locallang.xlf:filemounts', 'beuser'))
                ->setHref($this->uriBuilder->uriFor('filemounts', ...$beUserArgs))
                ->setActive(false)
        );

        $menu->addMenuItem(
            $menu->makeMenuItem()
                ->setTitle('Permission sets')
                ->setHref('#')
                ->setActive(true)
        );

        $view->getDocHeaderComponent()->getMenuRegistry()->addMenu($menu);
    }

    protected function addModuleDocHeaderButtons(ModuleTemplate $view): void
    {
        $buttonBar = $view->getDocHeaderComponent()->getButtonBar();

        $buttonBar
            ->addButton(
                $buttonBar->makeLinkButton()
                    ->setTitle((string)LocalizationUtility::translate('LLL:EXT:acl_enhancements/Resources/Private/Language/locallang_tca.xlf:permissionSets.addNewPreset'))
                    ->setShowLabelText(true)
                    ->setIcon($this->iconFactory->getIcon('actions-open', IconSize::SMALL))
                    ->setHref($this->uriBuilder->uriFor('create')),
                ButtonBar::BUTTON_POSITION_LEFT,
                0
            );
    }

    protected function addDocHeaderButtonsForDetailView(ModuleTemplate $view, int $beGroupUid, string $permissionSetIdentifier = ''): void
    {
        $buttonBar = $view->getDocHeaderComponent()->getButtonBar();

        if ($beGroupUid === 0) {
            $buttonBar->addButton(
                $buttonBar->makeLinkButton()
                    ->setTitle((string)LocalizationUtility::translate('LLL:EXT:acl_enhancements/Resources/Private/Language/locallang_tca.xlf:permissionSets.goBack'))
                    ->setShowLabelText(true)
                    ->setIcon($this->iconFactory->getIcon('actions-chevron-left', IconSize::SMALL))
                    ->setHref($this->uriBuilder->uriFor('index')),
                ButtonBar::BUTTON_POSITION_LEFT,
                0
            );
        } else {
            $buttonBar->addButton(
                $buttonBar->makeLinkButton()
                    ->setTitle((string)LocalizationUtility::translate('LLL:EXT:acl_enhancements/Resources/Private/Language/locallang_tca.xlf:permissionSets.openInEditor'))
                    ->setShowLabelText(true)
                    ->setIcon($this->iconFactory->getIcon('actions-open', IconSize::SMALL))
                    ->setHref(
                        (string)$this->backendUriBuilder->buildUriFromRoute(
                            'record_edit',
                            [
                                'edit' => ['be_groups' => [$beGroupUid => 'edit']],
                            ]
                        )
                    ),
                ButtonBar::BUTTON_POSITION_LEFT,
                0
            );
        }

        if ($permissionSetIdentifier !== '') {
            $buttonBar->addButton(
                $buttonBar->makeLinkButton()
                    ->setTitle('Export')
                    ->setShowLabelText(true)
                    ->setHref(
                        $this->uriBuilder->uriFor(
                            'download',
                            [
                                'presetIdentifier' => $permissionSetIdentifier,
                                'returnUrl' => $this->request->getUri(),
                            ]
                        )
                    )
                    ->setIcon($this->iconFactory->getIcon('actions-document-save', IconSize::SMALL)),
                ButtonBar::BUTTON_POSITION_LEFT,
                2
            );
        }

        $buttonBar->addButton(
            $buttonBar->makeInputButton()
                ->setTitle('Save')
                ->setName('_savedok')
                ->setValue('1')
                ->setShowLabelText(true)
                ->setForm('PresetsModuleController')
                ->setIcon($this->iconFactory->getIcon('actions-document-save', IconSize::SMALL)),
        );

        if ($permissionSetIdentifier !== '') {
            $buttonBar->addButton(
                $buttonBar->makeLinkButton()
                    ->setTitle((string)LocalizationUtility::translate('LLL:EXT:acl_enhancements/Resources/Private/Language/locallang_tca.xlf:permissionSets.delete'))
                    ->setShowLabelText(true)
                    ->setIcon($this->iconFactory->getIcon('actions-delete', IconSize::SMALL))
                    ->setHref(
                        $this->uriBuilder->uriFor(
                            'delete',
                            [
                                'presetIdentifier' => $permissionSetIdentifier,
                            ]
                        )
                    ),
                ButtonBar::BUTTON_POSITION_LEFT,
                3
            );
        }
    }

    protected function generateDownloadResponse(string $result, string $filename, string $absoluteFilename): ResponseInterface
    {
        if (!file_exists($absoluteFilename)) {
            throw new \Exception('File doesn\'t exist');
        }

        $response = $this->responseFactory->createResponse()
            ->withHeader('Content-Type', 'application/octet-stream')
            ->withHeader('Content-Disposition', 'attachment; filename=' . $filename);
        $response->getBody()->write($result);

        return $response;
    }

    protected function getFilenameCandidate(string $name): string
    {
        $filename = YamlFileUtility::sanitizeFilename($name);
        $uniqueFilepath = YamlFileUtility::uniqueFilenameInPath(
            $filename . '.' . YamlPermissionSetsWriter::FILE_EXTENSION,
            YamlFileUtility::getPermissionsDirectory()
        );

        return YamlFileUtility::getIdentifierFromPath($uniqueFilepath);
    }

    /**
     * @param array<int,string> $fields
     * @return array<array<string,mixed>>
     */
    protected function findBeGroupsUsingPreset(string $presetIdentifier, array $fields = ['uid']): array
    {
        if ($presetIdentifier === '') {
            return [];
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('be_groups');
        return $queryBuilder
            ->select(...$fields)
            ->from('be_groups')
            ->where(
                $queryBuilder->expr()->inSet('permission_sets', $queryBuilder->createNamedParameter($presetIdentifier))
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }
}
