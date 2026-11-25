<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Unit\Event;

use JWeiland\Pforum\Event\ModifyRichTextSanitizerConfigurationEvent;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 */
class ModifyRichTextSanitizerConfigurationEventTest extends UnitTestCase
{
    private ModifyRichTextSanitizerConfigurationEvent $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new ModifyRichTextSanitizerConfigurationEvent(
            ['p', 'strong'],
            ['b' => 'strong'],
            ['script'],
            ['div'],
            'tx_pforum_domain_model_topic',
            'description',
        );
    }

    #[Test]
    public function gettersReturnConstructorArguments(): void
    {
        self::assertSame(['p', 'strong'], $this->subject->getAllowedTags());
        self::assertSame(['b' => 'strong'], $this->subject->getMappedTags());
        self::assertSame(['script'], $this->subject->getRemovedTags());
        self::assertSame(['div'], $this->subject->getBlockTags());
        self::assertSame('tx_pforum_domain_model_topic', $this->subject->getTable());
        self::assertSame('description', $this->subject->getColumn());
    }

    #[Test]
    public function tableAndColumnAreEmptyByDefault(): void
    {
        $subject = new ModifyRichTextSanitizerConfigurationEvent([], [], [], []);

        self::assertSame('', $subject->getTable());
        self::assertSame('', $subject->getColumn());
    }

    #[Test]
    public function settersReplaceValues(): void
    {
        $this->subject->setAllowedTags(['p', 'em']);
        $this->subject->setMappedTags(['i' => 'em']);
        $this->subject->setRemovedTags(['style']);
        $this->subject->setBlockTags(['section']);

        self::assertSame(['p', 'em'], $this->subject->getAllowedTags());
        self::assertSame(['i' => 'em'], $this->subject->getMappedTags());
        self::assertSame(['style'], $this->subject->getRemovedTags());
        self::assertSame(['section'], $this->subject->getBlockTags());
        self::assertSame('description', $this->subject->getColumn());
    }
}
