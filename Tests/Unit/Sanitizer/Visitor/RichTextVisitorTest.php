<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Unit\Sanitizer\Visitor;

use JWeiland\Pforum\Event\ModifyRichTextSanitizerConfigurationEvent;
use JWeiland\Pforum\Sanitizer\RichTextSanitizerConfiguration;
use JWeiland\Pforum\Sanitizer\Visitor\RichTextVisitor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 */
class RichTextVisitorTest extends UnitTestCase
{
    private RichTextVisitor $subject;

    private \DOMDocument $document;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new RichTextVisitor(
            RichTextSanitizerConfiguration::fromEvent(new ModifyRichTextSanitizerConfigurationEvent(
                RichTextVisitor::ALLOWED_TAGS,
                RichTextVisitor::MAPPED_TAGS,
                RichTextVisitor::REMOVED_TAGS,
                RichTextVisitor::BLOCK_TAGS,
            )),
        );
        $this->document = new \DOMDocument();
    }

    private function createElement(string $tagName, string $text = 'text'): \DOMElement
    {
        $element = $this->document->createElement($tagName);
        $element->appendChild($this->document->createTextNode($text));

        return $element;
    }

    #[Test]
    public function textNodesAreKept(): void
    {
        $text = $this->document->createTextNode('foo');

        self::assertSame($text, $this->subject->enterNode($text));
    }

    #[Test]
    public function commentsCdataSectionsAndProcessingInstructionsAreRemoved(): void
    {
        self::assertNull($this->subject->enterNode($this->document->createComment('foo')));
        self::assertNull($this->subject->enterNode($this->document->createCDATASection('foo')));
        self::assertNull($this->subject->enterNode($this->document->createProcessingInstruction('php', 'echo 1;')));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function removedTagDataProvider(): array
    {
        return [
            'script' => ['script'],
            'upper case script' => ['SCRIPT'],
            'style' => ['style'],
            'iframe' => ['iframe'],
            'img' => ['img'],
            'form' => ['form'],
            'svg' => ['svg'],
        ];
    }

    #[Test]
    #[DataProvider('removedTagDataProvider')]
    public function elementsWithActiveOrEmbeddedContentAreRemoved(string $tagName): void
    {
        self::assertNull($this->subject->enterNode($this->createElement(strtolower($tagName))));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function allowedTagDataProvider(): array
    {
        return [
            'p' => ['p'],
            'strong' => ['strong'],
            'em' => ['em'],
            'h2' => ['h2'],
            'ul' => ['ul'],
            'li' => ['li'],
            'a' => ['a'],
            'br' => ['br'],
        ];
    }

    #[Test]
    #[DataProvider('allowedTagDataProvider')]
    public function allowedElementsAreKeptAsIs(string $tagName): void
    {
        $element = $this->createElement($tagName);

        self::assertSame($element, $this->subject->enterNode($element));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function mappedTagDataProvider(): array
    {
        return [
            'b' => ['b', 'strong'],
            'i' => ['i', 'em'],
            'h1' => ['h1', 'p'],
            'h4' => ['h4', 'p'],
            'h6' => ['h6', 'p'],
        ];
    }

    #[Test]
    #[DataProvider('mappedTagDataProvider')]
    public function mappedElementsAreRenamedAndLoseAttributes(string $tagName, string $expectedTagName): void
    {
        $element = $this->createElement($tagName, 'foo');
        $element->setAttribute('style', 'color: red');

        $result = $this->subject->enterNode($element);

        self::assertInstanceOf(\DOMElement::class, $result);
        self::assertSame($expectedTagName, $result->nodeName);
        self::assertSame('foo', $result->textContent);
        self::assertFalse($result->hasAttribute('style'));
    }

    #[Test]
    public function unknownInlineElementsAreRenamedToUnwrapTag(): void
    {
        $result = $this->subject->enterNode($this->createElement('span', 'foo'));

        self::assertInstanceOf(\DOMElement::class, $result);
        self::assertSame(RichTextVisitor::UNWRAP_TAG, $result->nodeName);
        self::assertSame('foo', $result->textContent);
    }

    #[Test]
    public function unknownBlockElementsWithoutBlockChildrenBecomeParagraphs(): void
    {
        $result = $this->subject->enterNode($this->createElement('div', 'foo'));

        self::assertInstanceOf(\DOMElement::class, $result);
        self::assertSame('p', $result->nodeName);
        self::assertSame('foo', $result->textContent);
    }

    #[Test]
    public function unknownBlockElementsWithBlockChildrenAreUnwrapped(): void
    {
        $div = $this->document->createElement('div');
        $div->appendChild($this->createElement('p', 'foo'));

        $result = $this->subject->enterNode($div);

        self::assertInstanceOf(\DOMElement::class, $result);
        self::assertSame(RichTextVisitor::UNWRAP_TAG, $result->nodeName);
        self::assertSame('p', $result->firstChild?->nodeName);
    }

    #[Test]
    public function leaveNodeKeepsOtherNodesUntouched(): void
    {
        $text = $this->document->createTextNode('foo');
        $paragraph = $this->createElement('p');

        self::assertSame($text, $this->subject->leaveNode($text));
        self::assertSame($paragraph, $this->subject->leaveNode($paragraph));
    }

    #[Test]
    public function linksWithoutHrefAreUnwrapped(): void
    {
        $result = $this->subject->leaveNode($this->createElement('a', 'link text'));

        self::assertInstanceOf(\DOMElement::class, $result);
        self::assertSame(RichTextVisitor::UNWRAP_TAG, $result->nodeName);
        self::assertSame('link text', $result->textContent);
    }

    #[Test]
    public function hrefOfLinksIsTrimmed(): void
    {
        $link = $this->createElement('a');
        $link->setAttribute('href', "  https://example.com \n");

        $result = $this->subject->leaveNode($link);

        self::assertInstanceOf(\DOMElement::class, $result);
        self::assertSame('https://example.com', $result->getAttribute('href'));
    }

    #[Test]
    public function linksWithTargetBlankGetNoopenerRel(): void
    {
        $link = $this->createElement('a');
        $link->setAttribute('href', 'https://example.com');
        $link->setAttribute('target', '_blank');
        $link->setAttribute('rel', 'nofollow');

        $result = $this->subject->leaveNode($link);

        self::assertInstanceOf(\DOMElement::class, $result);
        self::assertSame('_blank', $result->getAttribute('target'));
        self::assertSame('noopener noreferrer', $result->getAttribute('rel'));
    }

    #[Test]
    public function targetAndRelOfOtherLinksAreRemoved(): void
    {
        $link = $this->createElement('a');
        $link->setAttribute('href', 'https://example.com');
        $link->setAttribute('target', '_top');
        $link->setAttribute('rel', 'noopener');

        $result = $this->subject->leaveNode($link);

        self::assertInstanceOf(\DOMElement::class, $result);
        self::assertFalse($result->hasAttribute('target'));
        self::assertFalse($result->hasAttribute('rel'));
        self::assertSame('https://example.com', $result->getAttribute('href'));
    }
}
