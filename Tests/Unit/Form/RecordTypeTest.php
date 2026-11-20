<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Unit\Form;

use JWeiland\Pforum\Form\RecordType;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 */
class RecordTypeTest extends UnitTestCase
{
    #[Test]
    public function fromConfigurationReadsRenderingOption(): void
    {
        self::assertSame(RecordType::Post, RecordType::fromConfiguration(['renderingOptions' => ['recordType' => 'post']]));
    }

    #[Test]
    public function fromConfigurationOfOtherFormReturnsNull(): void
    {
        self::assertNull(RecordType::fromConfiguration([]));
        self::assertNull(RecordType::fromConfiguration(['renderingOptions' => ['recordType' => 'company']]));
        self::assertNull(RecordType::fromConfiguration(['renderingOptions' => ['recordType' => ['topic']]]));
    }

    #[Test]
    public function topicBelongsToForum(): void
    {
        self::assertSame('tx_pforum_domain_model_topic', RecordType::Topic->getTable());
        self::assertSame('forum', RecordType::Topic->getParentColumn());
        self::assertSame('tx_pforum_domain_model_forum', RecordType::Topic->getParentTable());
        self::assertSame('topics', RecordType::Topic->getParentCounterColumn());
    }

    #[Test]
    public function postBelongsToTopic(): void
    {
        self::assertSame('tx_pforum_domain_model_post', RecordType::Post->getTable());
        self::assertSame('topic', RecordType::Post->getParentColumn());
        self::assertSame('posts', RecordType::Post->getParentCounterColumn());
    }

    #[Test]
    public function tokensToEditAndToActivateDiffer(): void
    {
        self::assertSame('Topic', RecordType::Topic->getAccessTokenType());
        self::assertSame('TopicActivation', RecordType::Topic->getActivationTokenType());
    }
}
