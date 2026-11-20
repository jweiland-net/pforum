<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Unit\Form;

use JWeiland\Pforum\Form\FormOwner;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Form\Domain\Model\FormDefinition;
use TYPO3\CMS\Form\Domain\Model\FormElements\GenericFormElement;
use TYPO3\CMS\Form\Domain\Model\FormElements\Page;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 */
class FormOwnerTest extends UnitTestCase
{
    #[Test]
    public function markConfigurationSetsRenderingOption(): void
    {
        self::assertSame(
            ['renderingOptions' => ['submitButtonLabel' => 'Save', 'formOwner' => 'pforum']],
            FormOwner::markConfiguration(['renderingOptions' => ['submitButtonLabel' => 'Save', 'formOwner' => 'other']]),
        );
    }

    #[Test]
    public function isPforumFormWithOwnFormReturnsTrue(): void
    {
        self::assertTrue(FormOwner::isPforumForm($this->buildFormDefinition('pforum')));
    }

    #[Test]
    public function isPforumFormWithOtherFormReturnsFalse(): void
    {
        self::assertFalse(FormOwner::isPforumForm($this->buildFormDefinition('events2')));
        self::assertFalse(FormOwner::isPforumForm($this->buildFormDefinition('')));
        self::assertFalse(FormOwner::isPforumForm(null));
    }

    #[Test]
    public function isRenderableOfPforumFormWithElementOfOwnFormReturnsTrue(): void
    {
        $formDefinition = $this->buildFormDefinition('pforum');

        self::assertTrue(FormOwner::isRenderableOfPforumForm($formDefinition));
        self::assertTrue(FormOwner::isRenderableOfPforumForm($this->getDescription($formDefinition)));
    }

    #[Test]
    public function isRenderableOfPforumFormWithElementOfOtherFormReturnsFalse(): void
    {
        self::assertFalse(FormOwner::isRenderableOfPforumForm($this->getDescription($this->buildFormDefinition('events2'))));
        self::assertFalse(FormOwner::isRenderableOfPforumForm($this->getDescription($this->buildFormDefinition(''))));
    }

    #[Test]
    public function isRenderableOfPforumFormWithElementWithoutFormReturnsFalse(): void
    {
        self::assertFalse(FormOwner::isRenderableOfPforumForm(new GenericFormElement('description', 'Textarea')));
    }

    private function getDescription(FormDefinition $formDefinition): GenericFormElement
    {
        $element = $formDefinition->getElementByIdentifier('description');
        self::assertInstanceOf(GenericFormElement::class, $element);

        return $element;
    }

    private function buildFormDefinition(string $owner): FormDefinition
    {
        $formDefinition = new FormDefinition('form', [
            'formElementsDefinition' => [
                'Form' => [],
                'Page' => ['implementationClassName' => Page::class],
                'Textarea' => ['implementationClassName' => GenericFormElement::class],
            ],
        ]);
        if ($owner !== '') {
            $formDefinition->setRenderingOption(FormOwner::RENDERING_OPTION, $owner);
        }
        $formDefinition->createPage('page')->createElement('description', 'Textarea');

        return $formDefinition;
    }
}
