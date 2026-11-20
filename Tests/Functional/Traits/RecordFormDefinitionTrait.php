<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Functional\Traits;

use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Form\Domain\Model\FormDefinition;
use TYPO3\CMS\Form\Domain\Model\FormElements\GenericFormElement;
use TYPO3\CMS\Form\Domain\Model\FormElements\Page;

/**
 * Builds the topic or post form like the form definitions of pforum
 * without the prototype configuration of EXT:form
 */
trait RecordFormDefinitionTrait
{
    private function buildRecordFormDefinition(string $recordType): FormDefinition
    {
        $formDefinition = new FormDefinition('pforum' . ucfirst($recordType), [
            'formElementsDefinition' => [
                'Form' => [],
                'Page' => ['implementationClassName' => Page::class],
                'Text' => ['implementationClassName' => GenericFormElement::class],
                'Email' => ['implementationClassName' => GenericFormElement::class],
                'Textarea' => ['implementationClassName' => GenericFormElement::class],
            ],
        ]);

        $page = $formDefinition->createPage($recordType);
        foreach (['name', 'username', 'email', 'title', 'description'] as $identifier) {
            $page->createElement($identifier, $identifier === 'description' ? 'Textarea' : 'Text');
        }

        return $formDefinition;
    }

    /**
     * The option "elements" of the save finisher of the form definition
     *
     * @return array<string, array<string, mixed>>
     */
    private function getElementsConfiguration(string $recordType): array
    {
        $formDefinition = Yaml::parseFile(__DIR__ . '/../../../Configuration/Form/' . ucfirst($recordType) . '.form.yaml');

        return $formDefinition['finishers'][1]['options']['elements'];
    }
}
