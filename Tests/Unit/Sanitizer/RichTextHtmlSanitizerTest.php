<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Unit\Sanitizer;

use JWeiland\Pforum\Event\ModifyRichTextSanitizerConfigurationEvent;
use JWeiland\Pforum\Sanitizer\RichTextHtmlSanitizer;
use JWeiland\Pforum\Sanitizer\RichTextSanitizerConfiguration;
use JWeiland\Pforum\Sanitizer\Visitor\RichTextVisitor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\HtmlSanitizer\Behavior;
use TYPO3\HtmlSanitizer\Behavior\Tag;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 */
class RichTextHtmlSanitizerTest extends UnitTestCase
{
    private RichTextHtmlSanitizer $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $configuration = RichTextSanitizerConfiguration::fromEvent(new ModifyRichTextSanitizerConfigurationEvent(
            ['p', 'br', 'strong', 'em', 'h2', 'ul', 'li'],
            [],
            [],
            RichTextVisitor::BLOCK_TAGS,
        ));
        $behavior = (new Behavior())->withName('pforum-test')->withTags(
            new Tag(RichTextVisitor::UNWRAP_TAG, Tag::ALLOW_CHILDREN),
            new Tag('p', Tag::ALLOW_CHILDREN),
            new Tag('br'),
            new Tag('strong', Tag::ALLOW_CHILDREN),
            new Tag('em', Tag::ALLOW_CHILDREN),
            new Tag('h2', Tag::ALLOW_CHILDREN),
            new Tag('ul', Tag::ALLOW_CHILDREN),
            new Tag('li', Tag::ALLOW_CHILDREN),
        );

        $this->subject = new RichTextHtmlSanitizer($configuration, $behavior);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function htmlDataProvider(): array
    {
        return [
            'blocks are kept' => ['<p>a</p><h2>b</h2>', '<p>a</p><h2>b</h2>'],
            'plain text is wrapped into paragraph' => ['foo', '<p>foo</p>'],
            'inline elements are wrapped into paragraph' => ['<strong>a</strong> <em>b</em>', '<p><strong>a</strong> <em>b</em></p>'],
            'inline content between blocks is wrapped' => ['<h2>a</h2>b<ul><li>c</li></ul>d', '<h2>a</h2><p>b</p><ul><li>c</li></ul><p>d</p>'],
            'whitespace between blocks is removed' => ["<p>a</p>\n  \n<p>b</p>", '<p>a</p><p>b</p>'],
            'line breaks without text are removed' => ['<p>a</p><br><p>b</p>', '<p>a</p><p>b</p>'],
            'empty input' => ['', ''],
            'whitespace only' => ["  \n ", ''],
            'unwrap tag is replaced by children' => [
                '<p>a</p><' . RichTextVisitor::UNWRAP_TAG . '><p>b</p></' . RichTextVisitor::UNWRAP_TAG . '>',
                '<p>a</p><p>b</p>',
            ],
            'inline unwrap tag content is wrapped' => [
                '<' . RichTextVisitor::UNWRAP_TAG . '>a</' . RichTextVisitor::UNWRAP_TAG . '> b',
                '<p>a b</p>',
            ],
            'nested unwrap tags' => [
                '<' . RichTextVisitor::UNWRAP_TAG . '><' . RichTextVisitor::UNWRAP_TAG . '><p>a</p></'
                . RichTextVisitor::UNWRAP_TAG . '></' . RichTextVisitor::UNWRAP_TAG . '>',
                '<p>a</p>',
            ],
        ];
    }

    #[Test]
    #[DataProvider('htmlDataProvider')]
    public function sanitizeFinishesDom(string $html, string $expected): void
    {
        self::assertSame($expected, $this->subject->sanitize($html));
    }
}
