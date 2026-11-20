<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Functional\Form;

use JWeiland\Pforum\Form\FormOwner;
use JWeiland\Pforum\Tests\Functional\Fixtures\Classes\ForeignRichTextEditorHook;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Form\Domain\Model\FormDefinition;
use TYPO3\CMS\Form\Domain\Model\FormElements\FormElementInterface;
use TYPO3\CMS\Form\Domain\Model\FormElements\GenericFormElement;
use TYPO3\CMS\Form\Domain\Model\FormElements\Page;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;
use TYPO3\CMS\Form\Domain\Runtime\FormState;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * The hooks of EXT:form are registered globally. The hooks of pforum must
 * act on the forms of pforum only, even if other extensions use the same
 * data type "richText" for their forms.
 */
class FormOwnerTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'form',
        'rte_ckeditor',
    ];

    protected array $testExtensionsToLoad = [
        'jweiland/pforum',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/form']['beforeRendering'][1763900000]
            = ForeignRichTextEditorHook::class;
    }

    #[Test]
    public function ownFormGetsExactlyOneRichTextEditor(): void
    {
        $formDefinition = $this->buildFormDefinition(FormOwner::PFORUM);

        $this->renderForm($formDefinition);

        self::assertSame(['data-pforum-rich-text'], $this->getRichTextAttributes($formDefinition));
    }

    #[Test]
    public function formOfOtherExtensionGetsItsOwnRichTextEditorOnly(): void
    {
        $formDefinition = $this->buildFormDefinition(ForeignRichTextEditorHook::OWNER);

        $this->renderForm($formDefinition);

        self::assertSame([ForeignRichTextEditorHook::DATA_ATTRIBUTE], $this->getRichTextAttributes($formDefinition));
    }

    #[Test]
    public function formOfCoreIsNotModified(): void
    {
        $formDefinition = $this->buildFormDefinition('');

        $this->renderForm($formDefinition);

        self::assertSame([], $this->getRichTextAttributes($formDefinition));
        self::assertSame(['class' => 'form-control'], $this->getDescription($formDefinition)->getProperties()['fluidAdditionalAttributes']);
    }

    /**
     * Calls all registered "beforeRendering" hooks for each element like FormRuntime does
     */
    private function renderForm(FormDefinition $formDefinition): void
    {
        $formRuntime = $this->createMock(FormRuntime::class);
        $formRuntime->method('getFormDefinition')->willReturn($formDefinition);
        $formRuntime->method('getFormState')->willReturn(new FormState());
        $formRuntime->method('getRequest')->willReturn($this->createMock(Request::class));

        foreach ($formDefinition->getRenderablesRecursively() as $renderable) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/form']['beforeRendering'] ?? [] as $className) {
                $hook = GeneralUtility::makeInstance($className);
                if (method_exists($hook, 'beforeRendering')) {
                    $hook->beforeRendering($formRuntime, $renderable);
                }
            }
        }
    }

    /**
     * @return string[]
     */
    private function getRichTextAttributes(FormDefinition $formDefinition): array
    {
        $attributes = $this->getDescription($formDefinition)->getProperties()['fluidAdditionalAttributes'] ?? [];

        return array_values(array_filter(
            array_keys($attributes),
            static fn(string $attribute): bool => str_ends_with($attribute, '-rich-text'),
        ));
    }

    private function getDescription(FormDefinition $formDefinition): FormElementInterface
    {
        $description = $formDefinition->getElementByIdentifier('description');
        self::assertInstanceOf(FormElementInterface::class, $description);

        return $description;
    }

    /**
     * A form with a rich text element like the topic form. $owner is the
     * rendering option "formOwner".
     */
    private function buildFormDefinition(string $owner): FormDefinition
    {
        $formDefinition = new FormDefinition('contact', [
            'formElementsDefinition' => [
                'Form' => [],
                'Page' => ['implementationClassName' => Page::class],
                'Textarea' => ['implementationClassName' => GenericFormElement::class],
            ],
        ]);
        $formDefinition->setRenderingOption('recordType', 'topic');
        if ($owner !== '') {
            $formDefinition->setRenderingOption(FormOwner::RENDERING_OPTION, $owner);
        }

        $description = $formDefinition->createPage('topic')->createElement('description', 'Textarea');
        $description->setProperty('dbMapping', ['column' => 'description', 'dataType' => 'richText']);
        $description->setProperty('fluidAdditionalAttributes', ['class' => 'form-control']);

        return $formDefinition;
    }
}
