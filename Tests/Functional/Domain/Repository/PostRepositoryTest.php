<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Functional\Domain\Repository;

use JWeiland\Pforum\Domain\Model\Post;
use JWeiland\Pforum\Domain\Model\Topic;
use JWeiland\Pforum\Domain\Repository\PostRepository;
use JWeiland\Pforum\Domain\Repository\TopicRepository;
use JWeiland\Pforum\Tests\Functional\Traits\ExtbaseRequestTrait;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 */
class PostRepositoryTest extends FunctionalTestCase
{
    use ExtbaseRequestTrait;

    protected array $testExtensionsToLoad = [
        'jweiland/pforum',
    ];

    private PostRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/Forum.csv');
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/RepositoryRecords.csv');
        $this->createExtbaseRequest();
        $this->subject = $this->get(PostRepository::class);
    }

    #[Test]
    public function findByTopicReturnsVisiblePostsOfTopicSortedByCreationDateDescending(): void
    {
        self::assertSame([8, 1], $this->getUids($this->subject->findByTopic($this->getTopic(1))->toArray()));
        self::assertSame([3, 4], $this->getUids($this->subject->findByTopic($this->getTopic(4))->toArray()));
    }

    #[Test]
    public function findByTopicWithHiddenReturnsHiddenPostsOnStoragePage(): void
    {
        $uids = $this->getUids($this->subject->findByTopic($this->getTopic(1), true)->toArray());

        // Posts 1 and 2 have the same creation date
        self::assertSame(8, $uids[0]);
        self::assertEqualsCanonicalizing([8, 1, 2], $uids);
    }

    #[Test]
    public function findByTopicNeverReturnsDeletedPosts(): void
    {
        $topic = $this->getTopic(1);

        self::assertNotContains(5, $this->getUids($this->subject->findByTopic($topic)->toArray()));
        self::assertNotContains(5, $this->getUids($this->subject->findByTopic($topic, true)->toArray()));
        self::assertNotContains(7, $this->getUids($this->subject->findByTopic($topic, true)->toArray()));
    }

    #[Test]
    public function findAllHiddenIgnoresStoragePageAndSortsByTitle(): void
    {
        self::assertSame([6, 2], $this->getUids($this->subject->findAllHidden()->toArray()));
    }

    #[Test]
    public function findAllHiddenNeverReturnsVisibleOrDeletedPosts(): void
    {
        $uids = $this->getUids($this->subject->findAllHidden()->toArray());

        self::assertNotContains(1, $uids);
        self::assertNotContains(8, $uids);
        self::assertNotContains(7, $uids);
    }

    #[Test]
    public function findHiddenObjectFindsHiddenPostOnOtherStoragePage(): void
    {
        $post = $this->subject->findHiddenObject(6);

        self::assertInstanceOf(Post::class, $post);
        self::assertSame('Another hidden reply on other page', $post->getTitle());
        self::assertTrue($post->getHidden());
    }

    #[Test]
    public function findHiddenObjectFindsVisiblePost(): void
    {
        self::assertSame(1, $this->subject->findHiddenObject(1)?->getUid());
    }

    #[Test]
    public function findHiddenObjectReturnsNullForUnknownUid(): void
    {
        self::assertNull($this->subject->findHiddenObject(9999));
    }

    #[Test]
    public function findHiddenObjectReturnsNullForDeletedPost(): void
    {
        self::assertNull($this->subject->findHiddenObject(5));
        self::assertNull($this->subject->findHiddenObject(7));
    }

    private function getTopic(int $uid): Topic
    {
        $topic = $this->get(TopicRepository::class)->findHiddenObject($uid);
        self::assertInstanceOf(Topic::class, $topic);

        return $topic;
    }

    /**
     * @param Post[] $posts
     * @return int[]
     */
    private function getUids(array $posts): array
    {
        return array_map(static fn(Post $post): int => (int)$post->getUid(), $posts);
    }
}
