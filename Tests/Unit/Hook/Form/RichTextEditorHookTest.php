<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Unit\Hook\Form;

use JWeiland\Pforum\Event\ModifyRichTextSanitizerConfigurationEvent;
use JWeiland\Pforum\Form\FormOwner;
use JWeiland\Pforum\Form\RecordType;
use JWeiland\Pforum\Form\RichTextEditorOptions;
use JWeiland\Pforum\Hook\Form\RichTextEditorHook;
use JWeiland\Pforum\Sanitizer\RichTextSanitizer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Form\Domain\Model\FormDefinition;
use TYPO3\CMS\Form\Domain\Model\FormElements\GenericFormElement;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 */
class RichTextEditorHookTest extends UnitTestCase
{
    private MockObject $richTextEditorOptions;

    private MockObject $formRuntime;

    private RichTextEditorHook $subject;

    /**
     * @var ModifyRichTextSanitizerConfigurationEvent[]
     */
    private array $dispatchedEvents = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->richTextEditorOptions = $this->createMock(RichTextEditorOptions::class);
        $this->richTextEditorOptions
            ->method('getEditorOptions')
            ->willReturnOnConsecutiveCalls(['language' => 'de'], ['language' => 'en']);

        $this->formRuntime = $this->createMock(FormRuntime::class);
        $this->formRuntime->method('getRequest')->willReturn($this->createMock(Request::class));
        $ownFormDefinition = new FormDefinition('pforumTopic');
        $ownFormDefinition->setRenderingOption(FormOwner::RENDERING_OPTION, FormOwner::PFORUM);
        $ownFormDefinition->setRenderingOption(RecordType::RENDERING_OPTION, 'topic');
        $this->formRuntime->method('getFormDefinition')->willReturn($ownFormDefinition);

        $this->subject = new RichTextEditorHook($this->richTextEditorOptions, new RichTextSanitizer($this->createEventDispatcher()));
    }

    private function createEventDispatcher(): EventDispatcherInterface
    {
        $dispatchedEvents = &$this->dispatchedEvents;

        return new class ($dispatchedEvents) implements EventDispatcherInterface {
            /**
             * @param ModifyRichTextSanitizerConfigurationEvent[] $dispatchedEvents
             */
            public function __construct(private array &$dispatchedEvents) {}

            public function dispatch(object $event): object
            {
                if ($event instanceof ModifyRichTextSanitizerConfigurationEvent) {
                    $event->setAllowedTags([...$event->getAllowedTags(), 'h4']);
                    $this->dispatchedEvents[] = $event;
                }

                return $event;
            }
        };
    }

    #[Test]
    public function beforeRenderingPassesConfigurationOfColumnToEditorOptions(): void
    {
        $element = $this->buildElement('description', 'richText');
        $this->richTextEditorOptions
            ->expects($this->once())
            ->method('getEditorOptions')
            ->with(
                self::anything(),
                self::callback(static fn($configuration): bool => in_array('h4', $configuration->allowedTags, true)),
                'EXT:my_site/Resources/Public/Translations/',
            );

        $this->subject->beforeRendering($this->formRuntime, $element);

        self::assertCount(1, $this->dispatchedEvents);
        self::assertSame('tx_pforum_domain_model_topic', $this->dispatchedEvents[0]->getTable());
        self::assertSame('description', $this->dispatchedEvents[0]->getColumn());
    }

    #[Test]
    public function beforeRenderingAddsEditorToRichTextElement(): void
    {
        $element = $this->buildElement('description', 'richText');
        $this->richTextEditorOptions->expects($this->once())->method('getEditorOptions');

        $this->subject->beforeRendering($this->formRuntime, $element);

        self::assertSame(
            ['class' => 'x', 'data-pforum-rich-text' => '{"language":"de"}'],
            $element->getProperties()['fluidAdditionalAttributes'],
        );
    }

    #[Test]
    public function beforeRenderingAddsEditorToEachRichTextElement(): void
    {
        $firstElement = $this->buildElement('description', 'richText');
        $secondElement = $this->buildElement('program', 'richText');
        $this->richTextEditorOptions->expects($this->exactly(2))->method('getEditorOptions');

        $this->subject->beforeRendering($this->formRuntime, $firstElement);
        $this->subject->beforeRendering($this->formRuntime, $secondElement);

        self::assertSame('{"language":"de"}', $firstElement->getProperties()['fluidAdditionalAttributes']['data-pforum-rich-text']);
        self::assertSame('{"language":"en"}', $secondElement->getProperties()['fluidAdditionalAttributes']['data-pforum-rich-text']);
    }

    #[Test]
    public function beforeRenderingIgnoresPlainTextElement(): void
    {
        $element = $this->buildElement('title', 'plainText');
        $this->richTextEditorOptions->expects($this->never())->method('getEditorOptions');

        $this->subject->beforeRendering($this->formRuntime, $element);

        self::assertSame(['class' => 'x'], $element->getProperties()['fluidAdditionalAttributes']);
    }

    #[Test]
    public function beforeRenderingIgnoresFormDefinition(): void
    {
        $this->richTextEditorOptions->expects($this->never())->method('getEditorOptions');

        $this->subject->beforeRendering($this->formRuntime, new FormDefinition('pforumTopic'));
    }

    #[Test]
    public function beforeRenderingIgnoresRichTextElementOfFormOfOtherExtension(): void
    {
        $element = $this->buildElement('description', 'richText');
        $formRuntime = $this->createMock(FormRuntime::class);
        $formRuntime->method('getFormDefinition')->willReturn(new FormDefinition('contact'));
        $this->richTextEditorOptions->expects($this->never())->method('getEditorOptions');

        $this->subject->beforeRendering($formRuntime, $element);

        self::assertSame(['class' => 'x'], $element->getProperties()['fluidAdditionalAttributes']);
    }

    private function buildElement(string $identifier, string $dataType): GenericFormElement
    {
        $element = new GenericFormElement($identifier, 'Textarea');
        $element->setProperty('dbMapping', ['column' => 'description', 'dataType' => $dataType]);
        $element->setProperty('fluidAdditionalAttributes', ['class' => 'x']);
        $element->setProperty('richTextEditor', ['translationsPath' => 'EXT:my_site/Resources/Public/Translations/']);

        return $element;
    }
}
