<?php

declare(strict_types=1);

/*
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

namespace T3UX\AclEnhancements\Permission\Set\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use T3UX\AclEnhancements\Permission\RecordPermissionSetsApplyHandler;
use T3UX\AclEnhancements\Permission\Set\Event\RecordPermissionSetApplyEvent;
use T3UX\AclEnhancements\Permission\Set\Instructions\GetBackendPermissionInstructionInterface;
use T3UX\AclEnhancements\Permission\Set\PermissionSetInterface;
use TYPO3\CMS\Backend\Module\ModuleInterface;
use TYPO3\CMS\Backend\Module\ModuleProvider;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Authentication\Mfa\MfaProviderRegistry;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\DataHandling\TableColumnType;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Schema\Field\StaticSelectFieldType;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * Listen to {@see RecordPermissionSetApplyEvent} dispatched by {@see RecordPermissionSetsApplyHandler::apply()}
 * to process table `be_groups` related permission apply actions.
 *
 * @internal to be used within EXT:core and not part of the public core API.
 */
readonly class BackendUserGroupPermissionSetApplyHandler
{
    private const ALLOWED_FILE_AND_FOLDER_PERMISSIONS = [
        'addFile',
        'readFile',
        'writeFile',
        'copyFile',
        'moveFile',
        'renameFile',
        'replaceFile',
        'deleteFile',
        'addFolder',
        'readFolder',
        'writeFolder',
        'copyFolder',
        'moveFolder',
        'renameFolder',
        'deleteFolder',
        'recursivedeleteFolder',
    ];

    public function __construct(
        private LoggerInterface $logger,
        private ModuleProvider $moduleProvider,
        private MfaProviderRegistry $mfaProviderRegistry,
        private SiteFinder $siteFinder,
        private TcaSchemaFactory $tcaSchemaFactory,
        #[Autowire(service: 'cache.runtime')]
        private FrontendInterface $runtimeCache,
    ) {}

    /**
     * Processes module instructions in the form of
     *
     * ```yaml
     * modules: "*"   # all modules and submodules
     * ```
     *
     * or
     *
     * ```yaml
     * modules:
     *   module-one: "*"   # module and submodules of module-one
     *   module-two: true  # only single (sub)module
     * ```
     */
    #[AsEventListener(identifier: 'backend-user-groups-permission-set-apply-modules')]
    public function applyModules(RecordPermissionSetApplyEvent $event): void
    {
        /** @var PermissionSetInterface&GetBackendPermissionInstructionInterface $set */
        $set = $event->getPermissionSet();
        if (!$this->canApply($event, 'groupMods')
            || $set->getBackendPermissionInstruction()->modules === []
        ) {
            return;
        }
        $appliedRecord = $event->getAppliedRecord();
        $appliedRecord['groupMods'] = $this->expandModuleInstruction(
            $set,
            $appliedRecord['groupMods'] ?? ''
        );
        $event->setAppliedRecord($appliedRecord);
    }

    #[AsEventListener(identifier: 'backend-user-groups-permission-set-apply-sites')]
    public function applySites(RecordPermissionSetApplyEvent $event): void
    {
        /** @var PermissionSetInterface&GetBackendPermissionInstructionInterface $set */
        $set = $event->getPermissionSet();
        if (!$this->canApply($event, 'db_mountpoints')
            || ($sites = $set->getBackendPermissionInstruction()->sites) === []
        ) {
            return;
        }
        $appliedRecord = $event->getAppliedRecord();
        $finalSitesAndPages = $appliedRecord['db_mountpoints'] ?? '';
        if (!(is_array($finalSitesAndPages) || is_string($finalSitesAndPages))) {
            return;
        }
        if (is_string($finalSitesAndPages)) {
            $finalSitesAndPages = GeneralUtility::trimExplode(',', $finalSitesAndPages, true);
        }
        foreach ($sites as $siteOrPage) {
            $potentialPid = null;

            if (MathUtility::canBeInterpretedAsInteger($siteOrPage)) {
                $potentialPid = (int)$siteOrPage;
            } else {
                try {
                    $potentialPid = $this->siteFinder->getSiteByIdentifier((string)$siteOrPage)->getRootPageId();
                } catch (SiteNotFoundException) {
                    $this->logger->warning(
                        \sprintf(
                            '[%s] Invalid Site "%s". Skipping.',
                            $set->getIdentifier(),
                            $siteOrPage,
                        )
                    );
                }
            }

            if (is_int($potentialPid)) {
                $finalSitesAndPages[] = $potentialPid;
            }
        }
        $appliedRecord['db_mountpoints'] = implode(',', array_unique($finalSitesAndPages));
        $event->setAppliedRecord($appliedRecord);
    }

    /**
     * Handles file permissions. See {@see self::ALLOWED_FILE_AND_FOLDER_PERMISSIONS}
     */
    #[AsEventListener(identifier: 'backend-user-groups-permission-set-apply-file-permissions')]
    public function applyFilePermissions(RecordPermissionSetApplyEvent $event): void
    {
        /** @var PermissionSetInterface&GetBackendPermissionInstructionInterface $set */
        $set = $event->getPermissionSet();
        if (!$this->canApply($event, 'file_permissions')
            || ($filePermissions = $set->getBackendPermissionInstruction()->filePermissions) === []
        ) {
            return;
        }
        $appliedRecord = $event->getAppliedRecord();
        $appliedRecord['file_permissions'] = $this->mergePermissions(
            $appliedRecord['file_permissions'] ?? '',
            $filePermissions === ['*']
                ? self::ALLOWED_FILE_AND_FOLDER_PERMISSIONS
                : array_intersect(array_values(self::ALLOWED_FILE_AND_FOLDER_PERMISSIONS), $filePermissions)
        );
        $event->setAppliedRecord($appliedRecord);
    }

    /**
     * @todo Not existing tables and/or not known to TCA are added. Should that be avoided ?
     */
    #[AsEventListener(identifier: 'backend-user-groups-permission-set-apply-table-permissions')]
    public function applyTablePermissions(RecordPermissionSetApplyEvent $event): void
    {
        /** @var PermissionSetInterface&GetBackendPermissionInstructionInterface $set */
        $set = $event->getPermissionSet();
        if (!$this->canApply($event, 'tables_select', 'tables_modify')) {
            return;
        }
        $appliedRecord = $event->getAppliedRecord();
        $readableResources = $this->getAllowedResourcesForPermission($set, 'read');
        if ($readableResources !== null) {
            $appliedRecord['tables_select'] = $this->mergePermissions(
                $appliedRecord['tables_select'] ?? '',
                $readableResources,
            );
        }
        $writeableResources = $this->getAllowedResourcesForPermission($set, 'write');
        if ($writeableResources !== null) {
            $appliedRecord['tables_modify'] = $this->mergePermissions(
                $appliedRecord['tables_modify'] ?? '',
                $writeableResources,
            );
        }
        // select & modify are connected due to combined representation within form engine and
        // needs to be written together to ensure ignoring database values and not mixing them.
        if ($readableResources !== null || $writeableResources !== null) {
            $appliedRecord['tables_select'] ??= '';
            $appliedRecord['tables_modify'] ??= '';
        }
        $event->setAppliedRecord($appliedRecord);
    }

    #[AsEventListener(identifier: 'backend-user-groups-permission-set-apply-field-permissions')]
    public function applyFieldPermissions(RecordPermissionSetApplyEvent $event): void
    {
        /** @var PermissionSetInterface&GetBackendPermissionInstructionInterface $set */
        $set = $event->getPermissionSet();
        if (!$this->canApply($event, 'non_exclude_fields')
            || ($resources = $set->getBackendPermissionInstruction()->resources) === []
        ) {
            return;
        }
        $fieldNames = [];
        $hasFieldsDefintion = false;
        foreach ($resources as $tableName => $configurationForResource) {
            if (!isset($configurationForResource['fields'])) {
                continue;
            }
            $hasFieldsDefintion = true;
            if (!$this->tcaSchemaFactory->has($tableName)) {
                $this->logger->warning(
                    \sprintf(
                        '[%s] Invalid table "%s". Skipping.',
                        $set->getIdentifier(),
                        $tableName,
                    )
                );
                continue;
            }
            $schema = $this->tcaSchemaFactory->get($tableName);
            if ($configurationForResource['fields'] === ['*']) {
                foreach ($schema->getFields() as $fieldName => $fieldConfiguration) {
                    if ($fieldConfiguration->supportsAccessControl()) {
                        $fieldNames[] = $tableName . ':' . $fieldName;
                    }
                }
                continue;
            }
            foreach ($configurationForResource['fields'] as $fieldName) {
                if (!$schema->hasField($fieldName)) {
                    $this->logger->warning(
                        \sprintf(
                            '[%s] Invalid field "%s" for table "%s". Skipping.',
                            $set->getIdentifier(),
                            $fieldName,
                            $tableName,
                        )
                    );
                    continue;
                }
                $fieldNames[] = $tableName . ':' . $fieldName;
            }
        }
        if (!$hasFieldsDefintion) {
            return;
        }
        $appliedRecord = $event->getAppliedRecord();
        $appliedRecord['non_exclude_fields'] = $this->mergePermissions($appliedRecord['non_exclude_fields'] ?? '', $fieldNames);
        $event->setAppliedRecord($appliedRecord);
    }

    #[AsEventListener(identifier: 'backend-user-groups-permission-set-apply-type-permissions')]
    public function applyTableFieldExplicitAllowPermissions(RecordPermissionSetApplyEvent $event): void
    {
        /** @var PermissionSetInterface&GetBackendPermissionInstructionInterface $set */
        $set = $event->getPermissionSet();
        if (!$this->canApply($event, 'pagetypes_select', 'explicit_allowdeny')
            || $set->getBackendPermissionInstruction()->resources === []
        ) {
            return;
        }
        [
            'pageTypes' => $pageTypes,
            'tableFieldTypes' => $tableFieldTypes,
        ] = $this->getTableFieldTypePermissions($set);
        $appliedRecord = $event->getAppliedRecord();
        if ($pageTypes !== false) {
            $appliedRecord['pagetypes_select'] = $this->mergePermissions($appliedRecord['pagetypes_select'] ?? '', $pageTypes);
        }
        if ($tableFieldTypes !== false) {
            $appliedRecord['explicit_allowdeny'] = $this->mergePermissions($appliedRecord['explicit_allowdeny'] ?? '', $tableFieldTypes);
        }
        $event->setAppliedRecord($appliedRecord);
    }

    #[AsEventListener(identifier: 'backend-user-groups-permission-set-apply-mfa-providers')]
    public function applyMfaPermissions(RecordPermissionSetApplyEvent $event): void
    {
        /** @var PermissionSetInterface&GetBackendPermissionInstructionInterface $set */
        $set = $event->getPermissionSet();
        if (!$this->canApply($event, 'mfa_providers')
            || $set->getBackendPermissionInstruction()->mfaProviders === []
        ) {
            return;
        }
        $appliedRecord = $event->getAppliedRecord();
        $appliedRecord['mfa_providers'] = $this->mergePermissions(
            $appliedRecord['mfa_providers'] ?? '',
            $this->expandMfaProviderInstruction($set),
        );
        $event->setAppliedRecord($appliedRecord);
    }

    #[AsEventListener(identifier: 'backend-user-groups-permission-set-apply-languages')]
    public function applyLanguagePermissions(RecordPermissionSetApplyEvent $event): void
    {
        /** @var PermissionSetInterface&GetBackendPermissionInstructionInterface $set */
        $set = $event->getPermissionSet();
        if (!$this->canApply($event, 'allowed_languages')
            || $set->getBackendPermissionInstruction()->languages === []
        ) {
            return;
        }
        $appliedRecord = $event->getAppliedRecord();
        $appliedRecord['allowed_languages'] = $this->mergeIntegerPermissions(
            $appliedRecord['allowed_languages'] ?? '',
            $this->expandLanguages($set),
        );
        $event->setAppliedRecord($appliedRecord);
    }

    #[AsEventListener(identifier: 'backend-user-groups-permission-set-apply-settings')]
    public function applySettings(RecordPermissionSetApplyEvent $event): void
    {
        /** @var PermissionSetInterface&GetBackendPermissionInstructionInterface $set */
        $set = $event->getPermissionSet();
        if (!$this->canApply($event, 'TSconfig')
            || ($settings = $set->getBackendPermissionInstruction()->settings) === []
        ) {
            return;
        }
        $appliedRecord = $event->getAppliedRecord();
        $appliedRecord['TSconfig'] = $this->mergePermissions(
            $appliedRecord['TSconfig'] ?? '',
            $this->prepareTsConfig($settings),
            "\n\r",
        );
        $event->setAppliedRecord($appliedRecord);
    }

    #[AsEventListener(identifier: 'backend-user-groups-permission-set-apply-categories')]
    public function applyCategoriesPermissions(RecordPermissionSetApplyEvent $event): void
    {
        /** @var PermissionSetInterface&GetBackendPermissionInstructionInterface $set */
        $set = $event->getPermissionSet();
        if (!$this->canApply($event, 'category_perms')
            || ($categories = $set->getBackendPermissionInstruction()->categories) === []
        ) {
            return;
        }
        $appliedRecord = $event->getAppliedRecord();
        $categoryFromRecord = is_array($appliedRecord['category_perms'] ?? '')
            ? $appliedRecord['category_perms']
            : GeneralUtility::trimExplode(',', $appliedRecord['category_perms'] ?? '', true);

        $appliedRecord['category_perms'] = implode(
            ',',
            array_filter(
                array_unique(
                    array_map(
                        intval(...),
                        array_merge(
                            $categoryFromRecord,
                            $categories
                        ),
                    ),
                ),
                static fn(int $item): bool => $item > 0,
            ),
        );
        $event->setAppliedRecord($appliedRecord);
    }

    #[AsEventListener(identifier: 'backend-user-groups-permission-set-apply-custom-options')]
    public function applyCustomOptions(RecordPermissionSetApplyEvent $event): void
    {
        /** @var PermissionSetInterface&GetBackendPermissionInstructionInterface $set */
        $set = $event->getPermissionSet();
        if (!$this->canApply($event, 'custom_options')
            || ($customOptions = $set->getBackendPermissionInstruction()->customOptions) === []
        ) {
            return;
        }
        $appliedRecord = $event->getAppliedRecord();
        $recordCustomOptions = $appliedRecord['custom_options'] ?? '';
        if (!(is_string($recordCustomOptions) || is_array($recordCustomOptions))) {
            $recordCustomOptions = '';
        }
        $appliedRecord['custom_options'] = $this->mergePermissions(
            $recordCustomOptions,
            $customOptions,
        );
        $event->setAppliedRecord($appliedRecord);
    }

    #[AsEventListener(identifier: 'backend-user-groups-permission-set-apply-file-mountpoints')]
    public function applyFileMountPoints(RecordPermissionSetApplyEvent $event): void
    {
        /** @var PermissionSetInterface&GetBackendPermissionInstructionInterface $set */
        $set = $event->getPermissionSet();
        if (!$this->canApply($event, 'file_mountpoints')
            || ($fileMounts = $set->getBackendPermissionInstruction()->fileMounts) === []
        ) {
            return;
        }
        $appliedRecord = $event->getAppliedRecord();
        $recordFileMountpoints = $appliedRecord['file_mountpoints'] ?? '';
        if (!(is_string($recordFileMountpoints) || is_array($recordFileMountpoints))) {
            $recordFileMountpoints = '';
        }
        $appliedRecord['file_mountpoints'] = $this->mergePermissions(
            $recordFileMountpoints,
            $fileMounts,
        );
        $event->setAppliedRecord($appliedRecord);
    }

    private function canApply(RecordPermissionSetApplyEvent $event, string ...$fieldNames): bool
    {
        if ($event->getTableName() !== 'be_groups') {
            return false;
        }
        if ($event->getPermissionSet() instanceof GetBackendPermissionInstructionInterface === false) {
            return false;
        }
        $suitableFields = $event->getPermissionSet()->getSuitableTablesAndTableFields()['be_groups'] ?? [];
        if ($fieldNames === [] || !is_array($suitableFields) || $suitableFields === []
        ) {
            return false;
        }
        foreach ($fieldNames as $fieldName) {
            if (!in_array($fieldName, $suitableFields, true)) {
                return false;
            }
        }
        return true;
    }

    private function expandModuleInstruction(
        PermissionSetInterface&GetBackendPermissionInstructionInterface $permissionSet,
        array|string $currentModules,
    ): array|string {
        $finalModules = is_array($currentModules) ? $currentModules : GeneralUtility::trimExplode(',', $currentModules, true);
        $modules = $permissionSet->getBackendPermissionInstruction()->modules;
        if ($modules === ['*']) {
            foreach ($this->moduleProvider->getModules(null, false, false) as $module) {
                if ($module->isStandalone() || $module->hasParentModule()) {
                    $finalModules[] = $module->getIdentifier();
                }
            }
        } else {
            foreach ($modules as $moduleName => $allowedState) {
                if ($this->moduleProvider->isModuleRegistered($moduleName)) {
                    /** @var ModuleInterface $module */
                    $module = $this->moduleProvider->getModule($moduleName);
                    if ($allowedState === '*') {
                        if ($module->hasParentModule() && $module->hasSubModules()) {
                            $finalModules[] = $module->getIdentifier();
                        }
                        foreach ($module->getSubmodules() as $subModule) {
                            $finalModules[] = $subModule->getIdentifier();
                            foreach ($subModule->getSubmodules() as $thirdLevelModule) {
                                $finalModules[] = $thirdLevelModule->getIdentifier();
                            }
                        }
                        continue;
                    }
                    if ($allowedState === true && ($module->isStandalone() || $module->hasParentModule())) {
                        $finalModules[] = $moduleName;
                        continue;
                    }
                }
                $this->logger->warning(
                    \sprintf(
                        '[%s] Invalid or not enabled module "%s". Skipping.',
                        $permissionSet->getIdentifier(),
                        $moduleName,
                    )
                );
            }
        }
        $finalModules = array_unique($finalModules);
        return is_array($currentModules) ? $finalModules : implode(',', $finalModules);
    }

    private function expandMfaProviderInstruction(
        PermissionSetInterface&GetBackendPermissionInstructionInterface $permissionSet,
    ): array {
        $finalMfaProviders = [];
        $allowedMfaProviders = $permissionSet->getBackendPermissionInstruction()->mfaProviders;
        if ($allowedMfaProviders === ['*']) {
            return array_keys($this->mfaProviderRegistry->getProviders());
        }
        foreach ($allowedMfaProviders as $allowedMfaProvider) {
            if (!$this->mfaProviderRegistry->hasProvider($allowedMfaProvider)) {
                $this->logger->warning(
                    \sprintf(
                        '[%s] Invalid MFA provider "%s". Skipping.',
                        $permissionSet->getIdentifier(),
                        $allowedMfaProvider,
                    )
                );
                continue;
            }
            $finalMfaProviders[] = $allowedMfaProvider;
        }
        return $finalMfaProviders;
    }

    /**
     * @param array<string|int>|string $originalPermissions
     * @param array<string|int> $newPermissions
     */
    private function mergePermissions(array|string $originalPermissions, array $newPermissions, string $glue = ','): array|string
    {
        if (is_array($originalPermissions)) {
            return array_unique(array_merge($originalPermissions, $newPermissions));
        }
        return implode($glue, array_unique(array_merge(GeneralUtility::trimExplode($glue, $originalPermissions, true), $newPermissions)));
    }

    /**
     * @param array<string|int>|string $originalPermissions
     * @param array<string|int> $newPermissions
     */
    private function mergeIntegerPermissions(array|string $originalPermissions, array $newPermissions, string $glue = ','): array|string
    {
        if (is_array($originalPermissions)) {
            return array_unique(array_merge($originalPermissions, $newPermissions));
        }
        return implode($glue, array_unique(array_merge(GeneralUtility::intExplode($glue, $originalPermissions, true), $newPermissions)));
    }

    /**
     * @param PermissionSetInterface&GetBackendPermissionInstructionInterface $permissionSet
     * @return array<int, int>
     */
    private function expandLanguages(
        PermissionSetInterface&GetBackendPermissionInstructionInterface $permissionSet,
    ): array {
        $languages = [];
        $instanceLanguages = $this->fetchLanguagesForInstance();
        $allowedLanguages = $permissionSet->getBackendPermissionInstruction()->languages;
        if ($allowedLanguages === ['*']) {
            return array_keys($instanceLanguages);
        }
        foreach ($allowedLanguages as $language) {
            if (MathUtility::canBeInterpretedAsInteger($language)) {
                $languageId = (int)$language;
                if (array_key_exists($languageId, $instanceLanguages)) {
                    $languages[] = $languageId;
                    continue;
                }
            } elseif (in_array($language, $instanceLanguages, true)) {
                foreach ($instanceLanguages as $languageId => $locale) {
                    if ($locale === $language) {
                        $languages[] = $languageId;
                    }
                }
                continue;
            }
            $this->logger->warning(
                \sprintf(
                    '[%s] Invalid language "%s". Skipping.',
                    $permissionSet->getIdentifier(),
                    $language,
                )
            );
        }
        return array_unique($languages);
    }

    /**
     * @param array<string, string|int> $settings
     * @return array<string>
     */
    private function prepareTsConfig(array $settings): array
    {
        $settings = (new TypoScriptService())->convertPlainArrayToTypoScriptArray($settings);
        $flattenedSettings = ArrayUtility::flatten($settings, '', true);
        $tsConfig = [];
        foreach ($flattenedSettings as $key => $value) {
            // Allow full line adding without building flat structures,
            // for example comment lines or `@import` statements.
            if (MathUtility::canBeInterpretedAsInteger($key)) {
                $tsConfig[] = $value;
                continue;
            }
            $tsConfig[] = $key . ' = ' . $value;
        }
        return $tsConfig;
    }

    /**
     * @return array<int, string>
     */
    private function fetchLanguagesForInstance(): array
    {
        $cacheIdentifier = 'record-set-applier-instance-languages';
        /** @var array<int, string>|false $instanceLanguages */
        $instanceLanguages = $this->runtimeCache->get($cacheIdentifier);
        if (is_array($instanceLanguages)) {
            return $instanceLanguages;
        }
        $instanceLanguages = [];
        foreach ($this->siteFinder->getAllSites() as $site) {
            foreach ($site->getAllLanguages() as $language) {
                $instanceLanguages[$language->getLanguageId()] = (string)$language->getLocale();
            }
        }
        $this->runtimeCache->set($cacheIdentifier, $instanceLanguages);
        return $instanceLanguages;
    }

    /**
     * Get all allowed resources for a specific permission.
     */
    private function getAllowedResourcesForPermission(
        PermissionSetInterface&GetBackendPermissionInstructionInterface $permissionSet,
        string $permission,
    ): ?array {
        $resources = $permissionSet->getBackendPermissionInstruction()->resources;
        if ($resources === []) {
            return null;
        }
        $tables = [];
        $hasPermissionDefinition = false;
        foreach ($resources as $tableName => $details) {
            if (!is_array($details) || !array_key_exists('permissions', $details)) {
                continue;
            }
            $hasPermissionDefinition = true;
            $permissions = $details['permissions'];
            if (!is_array($permissions) || $permissions === [] || !$this->tcaSchemaFactory->has($tableName)) {
                continue;
            }
            if ($permissions === ['*'] || in_array($permission, $permissions, true)) {
                $tables[] = $tableName;
            }
        }
        return !$hasPermissionDefinition ? null : $tables;
    }

    /**
     * Get all possible table type permissions for TCA type select fields with `authMode = explicitAllow`,
     * and also pageTypes in case `pages.doktype` or all allowable table field types selected in preset by
     * using wildcard `*`.
     *
     * @return array{pageTypes: false|string[], tableFieldTypes: false|string[]}
     */
    private function getTableFieldTypePermissions(
        PermissionSetInterface&GetBackendPermissionInstructionInterface $permissionSet,
    ): array {
        $pageTypes = false;
        $tableFieldTypePermissions = false;
        $resources = $permissionSet->getBackendPermissionInstruction()->resources;
        foreach ($resources as $resourceTableName => $tableDetails) {
            if (!is_array($tableDetails) || !array_key_exists('selectFieldItems', $tableDetails)) {
                continue;
            }
            if ($resourceTableName === 'pages') {
                $pageTypes = [];
            } elseif ($tableFieldTypePermissions === false) {
                $tableFieldTypePermissions = [];
            }
            if (!$this->tcaSchemaFactory->has($resourceTableName)
                || !is_array($tableDetails['selectFieldItems'])
                || $tableDetails['selectFieldItems'] === []
            ) {
                continue;
            }
            $types = $tableDetails['selectFieldItems'];
            $allowedFieldTypes = [];
            $allFieldsAndTypes = $this->getTableExplicitAllowFieldsWithTypesAndPageTypes($resourceTableName);
            $tableSchema = $this->tcaSchemaFactory->get($resourceTableName);
            $typeFieldName = ($tableSchema->getSubSchemaDivisorField()?->getName() ?? '');
            if ($types === ['*']) {
                $allowedFieldTypes = $allFieldsAndTypes;
            } else {
                foreach ($types as $fieldName => $typeItems) {
                    $allTypeItems = array_map(strval(...), $allFieldsAndTypes[$fieldName] ?? []);
                    if ($allTypeItems === [] || !is_array($typeItems) || $typeItems === []) {
                        continue;
                    }
                    $allowedTypeItems = [];
                    if ($typeItems === ['*']) {
                        $allowedTypeItems = $allTypeItems;
                    } else {
                        foreach ($typeItems as $typeItem) {
                            if (!($resourceTableName === 'pages' && $fieldName === $typeFieldName)) {
                                $typeItem = sprintf(
                                    '%s:%s:%s',
                                    $resourceTableName,
                                    $fieldName,
                                    $typeItem,
                                );
                            }
                            if (!in_array((string)$typeItem, $allTypeItems, true)) {
                                continue;
                            }
                            $allowedTypeItems[] = (string)$typeItem;
                        }
                    }
                    $allowedFieldTypes[$fieldName] = $allowedTypeItems;
                }
            }
            foreach ($allowedFieldTypes as $fieldName => $typeItems) {
                // `pages.doktype` is also a table type field, but does not set `authMode = explicitAllow`
                // in TCA configuration. Instead of fetching normal TCA select items, we retrieve it from
                // TcaSchema subschema collection directly.
                if ($resourceTableName === 'pages' && $typeFieldName === $fieldName) {
                    $pageTypes = $typeItems;
                    continue;
                }
                // other table and fields
                $tableFieldTypePermissions = [
                    ...array_values($tableFieldTypePermissions),
                    ...array_values($typeItems),
                ];
            }
        }
        return [
            'pageTypes' => $pageTypes,
            'tableFieldTypes' => is_array($tableFieldTypePermissions) ? array_unique($tableFieldTypePermissions) : false,
        ];
    }

    /**
     * Retrieve table select fields configured with authMode `explicitAllow`, for example:
     *
     * - tt_content.CType
     * - tt_content.list_type
     *
     * This adopts the TCA item hook used for `be_groups.explicit_allowdeny`
     * to populate the select box when editing a backend user group record,
     * but using TcaSchema instead of the `$GLOBALS['TCA']`. Following methods
     * are adopted:
     *
     * - {@see TcaItemsProcessorFunctions::populateExplicitAuthValues()}
     * - {@see TcaItemsProcessorFunctions::getGroupedExplicitAuthFieldValues()}
     */
    private function getTableExplicitAllowFieldsWithTypesAndPageTypes(string $tableName): array
    {
        if (!$this->tcaSchemaFactory->has($tableName)) {
            return [];
        }
        $fieldTypes = [];
        $tableSchema = $this->tcaSchemaFactory->get($tableName);
        foreach ($tableSchema->getFieldsOfType(TableColumnType::SELECT) as $field) {
            if (!($field instanceof StaticSelectFieldType)
                || ($field->getConfiguration()['authMode'] ?? '') !== 'explicitAllow'
                || $field->getItems() === []
            ) {
                continue;
            }
            foreach ($field->getItems() as $selectItem) {
                $fieldName = $field->getName();
                $fieldTypes[$fieldName] ??= [];
                $fieldTypes[$fieldName][] = sprintf(
                    '%s:%s:%s',
                    $tableName,
                    $fieldName,
                    $selectItem->getValue(),
                );
            }
        }
        if ($tableName === 'pages'
            && $tableSchema->getSubSchemaDivisorField()?->getName() !== null
        ) {
            // `pages.doktype` is also a table type field, but does not set `authMode = explicitAllow`
            // in TCA configuration. Instead of fetching normal TCA select items, we retrieve it from
            // TcaSchema subschema collection directly.
            $fieldTypes[$tableSchema->getSubSchemaDivisorField()->getName()] = (count($tableSchema->getSubSchemata()) > 0)
                ? array_keys(iterator_to_array($tableSchema->getSubSchemata()))
                : [];
        }
        return $fieldTypes;
    }
}
