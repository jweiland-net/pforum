<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Functional\Domain\Repository;

use JWeiland\Pforum\Domain\Model\Forum;
use JWeiland\Pforum\Domain\Model\Topic;
use JWeiland\Pforum\Domain\Repository\ForumRepository;
use JWeiland\Pforum\Domain\Repository\TopicRepository;
use JWeiland\Pforum\Tests\Functional\Traits\ExtbaseRequestTrait;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 */
class TopicRepositoryTest extends FunctionalTestCase
{
    use ExtbaseRequestTrait;

    protected array $testExtensionsToLoad = [
        'jweiland/pforum',
    ];

    private TopicRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/Forum.csv');
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/RepositoryRecords.csv');
        $this->createExtbaseRequest();
        $this->subject = $this->get(TopicRepository::class);
    }

    #[Test]
    public function findByForumReturnsVisibleTopicsOfForumOnly(): void
    {
        self::assertSame([1], $this->getUids($this->subject->findByForum($this->getForum(1))->toArray()));
        self::assertSame([4], $this->getUids($this->subject->findByForum($this->getForum(2))->toArray()));
    }

    #[Test]
    public function findByForumWithHiddenReturnsHiddenTopicsSortedByCreationDateDescending(): void
    {
        self::assertSame(
            [1, 2, 3],
            $this->getUids($this->subject->findByForum($this->getForum(1), true)->toArray()),
        );
    }

    #[Test]
    public function findByForumNeverReturnsDeletedTopics(): void
    {
        $forum = $this->getForum(1);

        self::assertNotContains(5, $this->getUids($this->subject->findByForum($forum)->toArray()));
        self::assertNotContains(5, $this->getUids($this->subject->findByForum($forum, true)->toArray()));
        self::assertNotContains(7, $this->getUids($this->subject->findByForum($forum, true)->toArray()));
    }

    #[Test]
    public function findAllHiddenIgnoresStoragePageAndSortsByTitle(): void
    {
        self::assertSame([6, 2, 3], $this->getUids($this->subject->findAllHidden()->toArray()));
    }

    #[Test]
    public function findAllHiddenNeverReturnsVisibleOrDeletedTopics(): void
    {
        $uids = $this->getUids($this->subject->findAllHidden()->toArray());

        self::assertNotContains(1, $uids);
        self::assertNotContains(4, $uids);
        self::assertNotContains(7, $uids);
    }

    #[Test]
    public function findHiddenObjectFindsHiddenTopicOnOtherStoragePage(): void
    {
        $topic = $this->subject->findHiddenObject(6);

        self::assertInstanceOf(Topic::class, $topic);
        self::assertSame('Alpha topic on other page', $topic->getTitle());
        self::assertTrue($topic->getHidden());
    }

    #[Test]
    public function findHiddenObjectFindsVisibleTopic(): void
    {
        self::assertSame(1, $this->subject->findHiddenObject(1)?->getUid());
    }

    #[Test]
    public function findHiddenObjectFindsTopicByOtherProperty(): void
    {
        self::assertSame(2, $this->subject->findHiddenObject('Hidden topic of Jane', 'title')?->getUid());
    }

    #[Test]
    public function findHiddenObjectReturnsNullForUnknownUid(): void
    {
        self::assertNull($this->subject->findHiddenObject(9999));
    }

    #[Test]
    public function findHiddenObjectReturnsNullForDeletedTopic(): void
    {
        self::assertNull($this->subject->findHiddenObject(5));
        self::assertNull($this->subject->findHiddenObject(7));
    }

    private function getForum(int $uid): Forum
    {
        $forum = $this->get(ForumRepository::class)->findByIdentifier($uid);
        self::assertInstanceOf(Forum::class, $forum);

        return $forum;
    }

    /**
     * @param Topic[] $topics
     * @return int[]
     */
    private function getUids(array $topics): array
    {
        return array_map(static fn(Topic $topic): int => (int)$topic->getUid(), $topics);
    }
}
