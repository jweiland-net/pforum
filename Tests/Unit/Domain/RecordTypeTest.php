<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Unit\Domain;

use JWeiland\Pforum\Domain\Model\Post;
use JWeiland\Pforum\Domain\Model\Topic;
use JWeiland\Pforum\Domain\RecordType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 */
class RecordTypeTest extends UnitTestCase
{
    #[Test]
    public function fromRecordReturnsTopicForTopic(): void
    {
        self::assertSame(RecordType::Topic, RecordType::fromRecord(new Topic()));
    }

    #[Test]
    public function fromRecordReturnsPostForPost(): void
    {
        self::assertSame(RecordType::Post, RecordType::fromRecord(new Post()));
    }

    /**
     * @return array<string, array{string, ?RecordType}>
     */
    public static function controllerNameDataProvider(): array
    {
        return [
            'Topic' => ['Topic', RecordType::Topic],
            'Post' => ['Post', RecordType::Post],
            'already lower case' => ['topic', RecordType::Topic],
            'Forum has no record type' => ['Forum', null],
            'Administration has no record type' => ['Administration', null],
            'empty' => ['', null],
            'upper case does not match' => ['TOPIC', null],
        ];
    }

    #[Test]
    #[DataProvider('controllerNameDataProvider')]
    public function tryFromControllerNameResolvesRecordType(string $controllerName, ?RecordType $expected): void
    {
        self::assertSame($expected, RecordType::tryFromControllerName($controllerName));
    }

    /**
     * @return array<string, array{RecordType, string, string, string, string, string}>
     */
    public static function gettersDataProvider(): array
    {
        return [
            'topic' => [
                RecordType::Topic,
                'tx_pforum_domain_model_topic',
                'topic',
                'Topic',
                'Topic',
                'TopicActivation',
            ],
            'post' => [
                RecordType::Post,
                'tx_pforum_domain_model_post',
                'post',
                'Post',
                'Post',
                'PostActivation',
            ],
        ];
    }

    #[Test]
    #[DataProvider('gettersDataProvider')]
    public function gettersReturnValuesOfRecordType(
        RecordType $recordType,
        string $table,
        string $argumentName,
        string $controllerName,
        string $accessTokenType,
        string $activationTokenType,
    ): void {
        self::assertSame($table, $recordType->getTable());
        self::assertSame($argumentName, $recordType->getArgumentName());
        self::assertSame($controllerName, $recordType->getControllerName());
        self::assertSame($accessTokenType, $recordType->getAccessTokenType());
        self::assertSame($activationTokenType, $recordType->getActivationTokenType());
    }

    /**
     * @return array<string, array{RecordType, string, string}>
     */
    public static function messageKeyDataProvider(): array
    {
        return [
            'topic created' => [RecordType::Topic, 'Created', 'topicCreated'],
            'topic deleted' => [RecordType::Topic, 'Deleted', 'topicDeleted'],
            'post updated' => [RecordType::Post, 'Updated', 'postUpdated'],
            'post activated' => [RecordType::Post, 'Activated', 'postActivated'],
        ];
    }

    #[Test]
    #[DataProvider('messageKeyDataProvider')]
    public function getMessageKeyAppendsAction(RecordType $recordType, string $action, string $expected): void
    {
        self::assertSame($expected, $recordType->getMessageKey($action));
    }
}
