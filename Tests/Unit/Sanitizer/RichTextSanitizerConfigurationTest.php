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
use JWeiland\Pforum\Sanitizer\RichTextSanitizerConfiguration;
use JWeiland\Pforum\Sanitizer\Visitor\RichTextVisitor;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 */
class RichTextSanitizerConfigurationTest extends UnitTestCase
{
    /**
     * @param string[] $allowedTags
     * @param array<string, string> $mappedTags
     * @param string[] $removedTags
     * @param string[] $blockTags
     */
    private function create(
        array $allowedTags = [],
        array $mappedTags = [],
        array $removedTags = [],
        array $blockTags = [],
    ): RichTextSanitizerConfiguration {
        return RichTextSanitizerConfiguration::fromEvent(
            new ModifyRichTextSanitizerConfigurationEvent($allowedTags, $mappedTags, $removedTags, $blockTags),
        );
    }

    #[Test]
    public function tagNamesAreNormalizedToLowerCase(): void
    {
        $configuration = $this->create(['STRONG', ' Em '], [], ['SCRIPT'], ['DIV']);

        self::assertSame(['p', 'strong', 'em'], $configuration->allowedTags);
        self::assertContains('script', $configuration->removedTags);
        self::assertSame(['div'], $configuration->blockTags);
    }

    #[Test]
    public function paragraphIsAlwaysAllowed(): void
    {
        self::assertSame(['p'], $this->create()->allowedTags);
        self::assertSame(['p', 'em'], $this->create(['em'])->allowedTags);
    }

    #[Test]
    public function invalidTagNamesAreDropped(): void
    {
        $configuration = $this->create(['em', '', '1a', 'a b', '<script>', 'h2'], [], [], ['x-y', 'div']);

        self::assertSame(['p', 'em', 'h2'], $configuration->allowedTags);
        self::assertSame(['div'], $configuration->blockTags);
    }

    #[Test]
    public function duplicateTagNamesAreRemoved(): void
    {
        $configuration = $this->create(['em', 'EM', 'em', 'p']);

        self::assertSame(['p', 'em'], $configuration->allowedTags);
    }

    #[Test]
    public function activeContentCanNotBeAllowed(): void
    {
        $configuration = $this->create(['script', 'iframe', 'svg', 'em']);

        self::assertSame(['p', 'em'], $configuration->allowedTags);
        self::assertContains('script', $configuration->removedTags);
        self::assertContains('iframe', $configuration->removedTags);
        self::assertContains('svg', $configuration->removedTags);
    }

    #[Test]
    public function removedTagsTakePrecedenceOverAllowedTags(): void
    {
        $configuration = $this->create(['em', 'strong'], [], ['em']);

        self::assertSame(['p', 'strong'], $configuration->allowedTags);
        self::assertContains('em', $configuration->removedTags);
    }

    #[Test]
    public function paragraphCanBeRemoved(): void
    {
        self::assertSame(['em'], $this->create(['em'], [], ['p'])->allowedTags);
    }

    #[Test]
    public function removedTagsAreUnique(): void
    {
        $configuration = $this->create([], [], ['script', 'u', 'u']);

        self::assertSame(count($configuration->removedTags), count(array_unique($configuration->removedTags)));
        self::assertContains('u', $configuration->removedTags);
    }

    #[Test]
    public function tagsAreMappedIfTargetIsAllowedAndSourceIsNot(): void
    {
        $configuration = $this->create(['strong', 'em'], ['B' => 'Strong', 'i' => 'em']);

        self::assertSame(['b' => 'strong', 'i' => 'em'], $configuration->mappedTags);
    }

    #[Test]
    public function tagsAreNotMappedIfTargetIsNotAllowed(): void
    {
        $configuration = $this->create(['em'], ['b' => 'strong']);

        self::assertSame([], $configuration->mappedTags);
    }

    #[Test]
    public function tagsAreNotMappedIfSourceIsAllowed(): void
    {
        $configuration = $this->create(['b', 'strong'], ['b' => 'strong']);

        self::assertSame([], $configuration->mappedTags);
    }

    #[Test]
    public function tagsAreNotMappedIfSourceIsRemoved(): void
    {
        $configuration = $this->create(['strong'], ['b' => 'strong'], ['b']);

        self::assertSame([], $configuration->mappedTags);
    }

    #[Test]
    public function tagsAreNotMappedToRemovedTarget(): void
    {
        $configuration = $this->create(['strong'], ['b' => 'strong'], ['strong']);

        self::assertSame([], $configuration->mappedTags);
    }

    #[Test]
    public function tagsAreMappedToParagraphByDefault(): void
    {
        $configuration = $this->create(['p'], ['h1' => 'p']);

        self::assertSame(['h1' => 'p'], $configuration->mappedTags);
    }

    #[Test]
    public function topLevelBlockTagsAreAllowedBlockTagsWithoutChildBlocks(): void
    {
        $configuration = $this->create(
            ['p', 'h2', 'ul', 'li', 'td', 'strong', 'table'],
            [],
            [],
            ['p', 'h2', 'ul', 'li', 'td', 'div', 'table'],
        );

        self::assertSame(['p', 'h2', 'ul', 'table'], $configuration->getTopLevelBlockTags());
    }

    #[Test]
    public function topLevelBlockTagsIgnoreAllChildBlockTags(): void
    {
        $configuration = $this->create(
            ['caption', 'dd', 'dt', 'li', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr'],
            [],
            [],
            ['caption', 'dd', 'dt', 'li', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr'],
        );

        self::assertSame([], $configuration->getTopLevelBlockTags());
    }

    #[Test]
    public function defaultsOfRichTextVisitorResultInExpectedConfiguration(): void
    {
        $configuration = $this->create(
            RichTextVisitor::ALLOWED_TAGS,
            RichTextVisitor::MAPPED_TAGS,
            RichTextVisitor::REMOVED_TAGS,
            RichTextVisitor::BLOCK_TAGS,
        );

        self::assertSame(RichTextVisitor::ALLOWED_TAGS, $configuration->allowedTags);
        self::assertSame(RichTextVisitor::MAPPED_TAGS, $configuration->mappedTags);
        self::assertSame(['p', 'h2', 'h3', 'ul', 'ol'], $configuration->getTopLevelBlockTags());
        self::assertNotContains('img', $configuration->allowedTags);
        self::assertContains('img', $configuration->removedTags);
    }
}
