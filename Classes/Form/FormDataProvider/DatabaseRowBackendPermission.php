<?php

declare(strict_types=1);

/*
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

namespace T3UX\AclEnhancements\Form\FormDataProvider;

use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use T3UX\AclEnhancements\Permission\ApplyMode;
use T3UX\AclEnhancements\Permission\RecordPermissionSetsApplyHandlerInterface;
use TYPO3\CMS\Backend\Form\FormDataProvider\AbstractDatabaseRecordProvider;
use TYPO3\CMS\Backend\Form\FormDataProviderInterface;
use TYPO3\CMS\Backend\Routing\RouteResult;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Routing\SiteRouteResult;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;

/**
 * @internal for backend internal usage only and not part of public API.
 */
#[Autoconfigure(public: true)]
class DatabaseRowBackendPermission extends AbstractDatabaseRecordProvider implements FormDataProviderInterface
{
    public function __construct(
        private readonly RecordPermissionSetsApplyHandlerInterface $recordSetApplier,
        private readonly TcaSchemaFactory $tcaSchemaFactory,
    ) {}

    /**
     * @param array<mixed> $result
     * @return array<mixed>
     * @throws \TYPO3\CMS\Core\Schema\Exception\UndefinedSchemaException
     */
    public function addData(array $result): array
    {
        return $this->shouldBlockFields($result) ? $this->blockFields($result) : $result;
    }

    /**
     * @param array<mixed> $result
     * @throws \TYPO3\CMS\Core\Schema\Exception\UndefinedSchemaException
     */
    private function shouldBlockFields(array $result): bool
    {
        if ($result['tableName'] !== 'be_groups') {
            return false;
        }

        if ($result['command'] !== 'new' && $result['command'] !== 'edit') {
            return false;
        }

        if (!$this->tcaSchemaFactory->has($result['tableName']) || !$this->tcaSchemaFactory->get($result['tableName'])->hasField('permission_sets')) {
            return false;
        }

        return (string)($result['databaseRow']['permission_sets'] ?? '') !== '';
    }

    /**
     * @param array<string,mixed> $result
     * @return mixed[]
     * @throws \TYPO3\CMS\Core\Schema\Exception\UndefinedSchemaException
     */
    private function blockFields(array $result): array
    {
        $tcaSchema = $this->tcaSchemaFactory->get($result['tableName']);
        $applyResult = $this->recordSetApplier->apply(ApplyMode::DYNAMIC, $result['tableName'], $result['databaseRow'] ?? []);

        if ($applyResult->appliedFields !== []) {
            $result['databaseRow'] = $applyResult->record;
            foreach (array_filter($applyResult->appliedFields, static fn(string $fieldName): bool => $tcaSchema->hasField($fieldName)) as $fieldName) {
                $result = $this->setFieldPermissionSetManaged($result, $fieldName);
            }
        }

        return $this->setGlobalPermissionSetManagedInformation($result);
    }

    /**
     * @param array<mixed> $result
     * @return array<mixed>
     */
    private function setGlobalPermissionSetManagedInformation(array $result): array
    {
        if (($this->getCurrentPathFromRouting() ?? '') !== '/record/edit') {
            return $result;
        }

        $result['processedTca']['ctrl']['container']['outerWrapContainer']['fieldInformation']['permissionSetManagedInformation'] = [
            'renderType' => 'permissionSetManagedInformation',
            'options' => [
                'translationKey' => 'LLL:EXT:acl_enhancements/Resources/Private/Language/locallang_core.xlf:formengine.permissionSetManagedRecord',
            ],
        ];

        return $result;
    }

    /**
     * @param array<mixed> $result
     * @return array<mixed>
     */
    private function setFieldPermissionSetManaged(array $result, string $fieldName): array
    {
        if (($this->getCurrentPathFromRouting() ?? '') !== '/record/edit') {
            return $result;
        }

        if (is_array($result['processedTca']['columns'][$fieldName] ?? null)) {
            $result['processedTca']['columns'][$fieldName]['config']['readOnly'] = true;
            $result['processedTca']['columns'][$fieldName]['config']['fieldInformation']['permissionSetManagedInformation'] = [
                'renderType' => 'permissionSetManagedInformation',
                'options' => [
                    'translationKey' => 'LLL:EXT:acl_enhancements/Resources/Private/Language/locallang_core.xlf:formengine.permissionSetManagedField',
                ],
            ];
        }
        return $result;
    }
    protected function getCurrentPathFromRouting(): ?string
    {
        /**
         * @var RouteResult|PageArguments|SiteRouteResult|null $routing
         * @phpstan-ignore varTag.type
         */
        $routing = $this->getRequest()->getAttribute('routing');
        if (!$routing instanceof RouteResult) {
            return null;
        }

        return $routing->getRoute()->getPath();
    }

    protected function getRequest(): ServerRequestInterface
    {
        return $GLOBALS['TYPO3_REQUEST'];
    }
}
