<?php

declare(strict_types=1);

/*
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

namespace T3UX\AclEnhancements\Form\Element;

use TYPO3\CMS\Backend\Form\Element\AbstractFormElement;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Utility\StringUtility;

class PresetUsedInFieldElement extends AbstractFormElement
{
    public function __construct(protected UriBuilder $uriBuilder) {}

    /**
     * @return array<mixed>
     */
    public function render(): array
    {
        $html = [];
        $parameterArray = $this->data['parameterArray'];

        $fieldInformationResult = $this->renderFieldInformation();
        $resultArray = $this->mergeChildReturnIntoExistingResult($this->initializeResultArray(), $fieldInformationResult, false);

        $items = ($parameterArray['fieldConf']['config']['items'] ?? []);

        if ($items === []) {
            return [];
        }

        $fieldId = StringUtility::getUniqueId('formengine-textarea-');
        $html[] = '<div class="form-wizards-wrap">';
        $html[] = $this->renderLabel($fieldId);

        foreach (($parameterArray['fieldConf']['config']['items'] ?? []) as $item) {
            $href = (string)$this->uriBuilder->buildUriFromRoute('record_edit', [
                'edit' => [
                    'be_groups' => [
                        $item['value'] => 'edit',
                    ],
                ],
                'returnUrl' => $GLOBALS['TYPO3_REQUEST']->getUri(),
            ]);
            $html[] = '<div>- <u><a href="' . $href . '"> ' . $item['label'] . ' [uid: ' . $item['value'] . ']</a></div></u>';
        }

        $html[] = '</div>';
        $resultArray['html'] = implode(LF, $html);

        return $resultArray;
    }
}
