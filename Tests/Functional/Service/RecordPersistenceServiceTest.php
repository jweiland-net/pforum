<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Functional\Service;

use JWeiland\Pforum\Domain\Model\Forum;
use JWeiland\Pforum\Domain\Model\Post;
use JWeiland\Pforum\Domain\Model\Topic;
use JWeiland\Pforum\Domain\RecordType;
use JWeiland\Pforum\Domain\Repository\ForumRepository;
use JWeiland\Pforum\Domain\Repository\TopicRepository;
use JWeiland\Pforum\Service\RecordPersistenceService;
use JWeiland\Pforum\Tests\Functional\Traits\ExtbaseRequestTrait;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 */
class RecordPersistenceServiceTest extends FunctionalTestCase
{
    use ExtbaseRequestTrait;

    protected array $testExtensionsToLoad = [
        'jweiland/pforum',
    ];

    private RecordPersistenceService $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Forum.csv');
        $this->createExtbaseRequest();
        $this->subject = $this->get(RecordPersistenceService::class);
    }

    #[Test]
    public function findReturnsHiddenTopicAndPost(): void
    {
        self::assertSame(2, $this->subject->find(RecordType::Topic, 2)?->getUid());
        self::assertSame(2, $this->subject->find(RecordType::Post, 2)?->getUid());
    }

    #[Test]
    public function findReturnsNullForUnknownAndInvalidUid(): void
    {
        self::assertNull($this->subject->find(RecordType::Topic, 9999));
        self::assertNull($this->subject->find(RecordType::Post, 9999));
        self::assertNull($this->subject->find(RecordType::Topic, 0));
        self::assertNull($this->subject->find(RecordType::Post, -1));
    }

    #[Test]
    public function persistOfNewTopicStoresTopicWithPathSegment(): void
    {
        $topic = new Topic();
        $topic->setTitle('Brand new topic');
        $topic->setDescription('<p>Question</p>');
        $topic->setHidden(true);
        $this->addToForum($topic);

        $this->subject->persist($topic, true);

        self::assertGreaterThan(0, (int)$topic->getUid());
        $record = $this->getRecord('topic', (int)$topic->getUid());
        self::assertSame('Brand new topic', $record['title']);
        self::assertSame(1, (int)$record['forum']);
        self::assertSame(1, (int)$record['hidden']);
        self::assertSame('brand-new-topic', $record['path_segment']);
    }

    #[Test]
    public function persistOfNewPostWithoutTitleStoresRecordTypeAndUidAsPathSegment(): void
    {
        $topic = $this->get(TopicRepository::class)->findHiddenObject(1);
        self::assertInstanceOf(Topic::class, $topic);
        $post = new Post();
        $post->setDescription('<p>Answer</p>');
        $post->setTopic($topic);
        $topic->addPost($post);
        $this->get(TopicRepository::class)->update($topic);

        $this->subject->persist($post, true);

        self::assertSame('post-' . $post->getUid(), $this->getRecord('post', (int)$post->getUid())['path_segment']);
    }

    #[Test]
    public function persistOfPublishedTopicKeepsPathSegment(): void
    {
        $topic = $this->subject->find(RecordType::Topic, 1);
        self::assertInstanceOf(Topic::class, $topic);
        $this->subject->persist($topic, true);
        self::assertSame('welcome', $this->getRecord('topic', 1)['path_segment']);

        $topic->setTitle('Renamed topic');
        $this->subject->persist($topic, false);

        $record = $this->getRecord('topic', 1);
        self::assertSame('Renamed topic', $record['title']);
        self::assertSame('welcome', $record['path_segment']);
    }

    #[Test]
    public function persistOfNewRecordGeneratesNewPathSegmentOnEachSave(): void
    {
        $topic = $this->subject->find(RecordType::Topic, 1);
        self::assertInstanceOf(Topic::class, $topic);
        $this->subject->persist($topic, true);

        $topic->setTitle('Changed in preview');
        $this->subject->persist($topic, true);

        self::assertSame('changed-in-preview', $this->getRecord('topic', 1)['path_segment']);
    }

    #[Test]
    public function activateUnhidesTopic(): void
    {
        $topic = $this->subject->find(RecordType::Topic, 2);
        self::assertInstanceOf(Topic::class, $topic);
        self::assertTrue($topic->getHidden());

        $this->subject->activate($topic);
        $this->get(PersistenceManagerInterface::class)->persistAll();

        self::assertSame(0, (int)$this->getRecord('topic', 2)['hidden']);
        self::assertFalse($topic->getHidden());
    }

    #[Test]
    public function activateUnhidesPost(): void
    {
        $post = $this->subject->find(RecordType::Post, 2);
        self::assertInstanceOf(Post::class, $post);

        $this->subject->activate($post);
        $this->get(PersistenceManagerInterface::class)->persistAll();

        self::assertSame(0, (int)$this->getRecord('post', 2)['hidden']);
    }

    #[Test]
    public function removeMarksTopicAsDeleted(): void
    {
        $topic = $this->subject->find(RecordType::Topic, 3);
        self::assertInstanceOf(Topic::class, $topic);

        $this->subject->remove($topic);
        $this->get(PersistenceManagerInterface::class)->persistAll();

        self::assertSame(1, (int)$this->getRecord('topic', 3)['deleted']);
        self::assertNull($this->subject->find(RecordType::Topic, 3));
    }

    #[Test]
    public function removeMarksPostAsDeleted(): void
    {
        $post = $this->subject->find(RecordType::Post, 2);
        self::assertInstanceOf(Post::class, $post);

        $this->subject->remove($post);
        $this->get(PersistenceManagerInterface::class)->persistAll();

        self::assertSame(1, (int)$this->getRecord('post', 2)['deleted']);
        self::assertSame(0, (int)$this->getRecord('post', 1)['deleted']);
    }

    #[Test]
    public function assignFrontendUserSetsFrontendUserOfRecord(): void
    {
        $topic = new Topic();

        $this->subject->assignFrontendUser($topic, 5);

        self::assertSame(5, $topic->getFrontendUser()?->getUid());
        self::assertSame('Owner', $topic->getUser()?->getName());
    }

    #[Test]
    public function assignFrontendUserIgnoresUnknownAndMissingUser(): void
    {
        $topic = new Topic();

        $this->subject->assignFrontendUser($topic, 0);
        $this->subject->assignFrontendUser($topic, 9999);

        self::assertNull($topic->getFrontendUser());
    }

    private function addToForum(Topic $topic): void
    {
        $forum = $this->get(ForumRepository::class)->findByIdentifier(1);
        self::assertInstanceOf(Forum::class, $forum);
        $topic->setForum($forum);
        $forum->addTopic($topic);
        $this->get(ForumRepository::class)->update($forum);
    }

    /**
     * @return array<string, mixed>
     */
    private function getRecord(string $recordType, int $uid): array
    {
        $table = 'tx_pforum_domain_model_' . $recordType;
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        $record = $queryBuilder
            ->select('*')
            ->from($table)
            ->where($queryBuilder->expr()->eq('uid', $uid))
            ->executeQuery()
            ->fetchAssociative();

        return is_array($record) ? $record : [];
    }
}
