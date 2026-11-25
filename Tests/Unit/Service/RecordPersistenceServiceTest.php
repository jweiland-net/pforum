<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Unit\Service;

use JWeiland\Pforum\Domain\Model\FrontendUser;
use JWeiland\Pforum\Domain\Model\Post;
use JWeiland\Pforum\Domain\Model\Topic;
use JWeiland\Pforum\Domain\RecordType;
use JWeiland\Pforum\Domain\Repository\FrontendUserRepository;
use JWeiland\Pforum\Domain\Repository\PostRepository;
use JWeiland\Pforum\Domain\Repository\TopicRepository;
use JWeiland\Pforum\Service\PathSegmentService;
use JWeiland\Pforum\Service\RecordPersistenceService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\Session;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 */
class RecordPersistenceServiceTest extends UnitTestCase
{
    private TopicRepository&MockObject $topicRepository;

    private PostRepository&MockObject $postRepository;

    private FrontendUserRepository&MockObject $frontendUserRepository;

    private PersistenceManagerInterface&MockObject $persistenceManager;

    private Session&MockObject $session;

    private PathSegmentService&MockObject $pathSegmentService;

    private LoggerInterface&MockObject $logger;

    private RecordPersistenceService $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->topicRepository = $this->createMock(TopicRepository::class);
        $this->postRepository = $this->createMock(PostRepository::class);
        $this->frontendUserRepository = $this->createMock(FrontendUserRepository::class);
        $this->persistenceManager = $this->createMock(PersistenceManagerInterface::class);
        $this->session = $this->createMock(Session::class);
        $this->pathSegmentService = $this->createMock(PathSegmentService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subject = new RecordPersistenceService(
            $this->topicRepository,
            $this->postRepository,
            $this->frontendUserRepository,
            $this->persistenceManager,
            $this->session,
            $this->pathSegmentService,
            $this->logger,
        );
    }

    private function createTopic(?int $uid): Topic
    {
        $topic = new Topic();
        if ($uid !== null) {
            $topic->_setProperty('uid', $uid);
        }

        return $topic;
    }

    private function createPost(?int $uid): Post
    {
        $post = new Post();
        if ($uid !== null) {
            $post->_setProperty('uid', $uid);
        }

        return $post;
    }

    #[Test]
    public function findReturnsNullForUidZeroOrNegative(): void
    {
        $this->topicRepository->expects($this->never())->method('findHiddenObject');
        $this->postRepository->expects($this->never())->method('findHiddenObject');

        self::assertNull($this->subject->find(RecordType::Topic, 0));
        self::assertNull($this->subject->find(RecordType::Post, -5));
    }

    #[Test]
    public function findDelegatesToTopicRepository(): void
    {
        $topic = $this->createTopic(3);
        $this->topicRepository->expects($this->once())->method('findHiddenObject')->with(3)->willReturn($topic);
        $this->postRepository->expects($this->never())->method('findHiddenObject');

        self::assertSame($topic, $this->subject->find(RecordType::Topic, 3));
    }

    #[Test]
    public function findDelegatesToPostRepository(): void
    {
        $post = $this->createPost(4);
        $this->postRepository->expects($this->once())->method('findHiddenObject')->with(4)->willReturn($post);
        $this->topicRepository->expects($this->never())->method('findHiddenObject');

        self::assertSame($post, $this->subject->find(RecordType::Post, 4));
    }

    #[Test]
    public function findReturnsNullIfRecordDoesNotExist(): void
    {
        $this->topicRepository->method('findHiddenObject')->willReturn(null);

        self::assertNull($this->subject->find(RecordType::Topic, 99));
    }

    #[Test]
    public function registerForPropertyMappingRegistersRecordInSessionWithUidAsString(): void
    {
        $topic = $this->createTopic(8);
        $this->session->expects($this->once())->method('registerObject')->with($topic, '8');

        $this->subject->registerForPropertyMapping($topic);
    }

    #[Test]
    public function assignFrontendUserSetsFoundUserAsAuthor(): void
    {
        $frontendUser = new FrontendUser();
        $this->frontendUserRepository->expects($this->once())->method('findByIdentifier')->with(5)->willReturn($frontendUser);
        $topic = $this->createTopic(null);
        $post = $this->createPost(null);

        $this->subject->assignFrontendUser($topic, 5);

        self::assertSame($frontendUser, $topic->getFrontendUser());
        self::assertNull($post->getFrontendUser());
    }

    #[Test]
    public function assignFrontendUserWithoutLoginDoesNotQueryRepository(): void
    {
        $this->frontendUserRepository->expects($this->never())->method('findByIdentifier');
        $post = $this->createPost(null);

        $this->subject->assignFrontendUser($post, 0);

        self::assertNull($post->getFrontendUser());
    }

    #[Test]
    public function assignFrontendUserKeepsAuthorIfUserIsNotFound(): void
    {
        $this->frontendUserRepository->method('findByIdentifier')->willReturn(null);
        $post = $this->createPost(null);

        $this->subject->assignFrontendUser($post, 5);

        self::assertNull($post->getFrontendUser());
    }

    #[Test]
    public function persistUpdatesExistingRecordsAndGeneratesPathSegment(): void
    {
        $topic = $this->createTopic(7);
        $this->persistenceManager->expects($this->once())->method('update')->with($topic);
        $this->persistenceManager->expects($this->once())->method('persistAll');
        $this->pathSegmentService->expects($this->once())->method('generate')->with(RecordType::Topic, 7, false);

        $this->subject->persist($topic, false);
    }

    #[Test]
    public function persistDoesNotUpdateNewRecordsBeforeFirstPersist(): void
    {
        $post = $this->createPost(null);
        $this->persistenceManager->expects($this->never())->method('update');
        $this->persistenceManager->expects($this->once())->method('persistAll');
        $this->pathSegmentService->expects($this->once())->method('generate')->with(RecordType::Post, 0, true);

        $this->subject->persist($post, true);
    }

    #[Test]
    public function persistPersistsAfterUpdateAndBeforeGeneratingPathSegment(): void
    {
        $calls = [];
        $this->persistenceManager->method('update')->willReturnCallback(static function () use (&$calls): void {
            $calls[] = 'update';
        });
        $this->persistenceManager->method('persistAll')->willReturnCallback(static function () use (&$calls): void {
            $calls[] = 'persistAll';
        });
        $this->pathSegmentService->method('generate')->willReturnCallback(static function () use (&$calls): void {
            $calls[] = 'generate';
        });

        $this->subject->persist($this->createPost(2), true);

        self::assertSame(['update', 'persistAll', 'generate'], $calls);
    }

    #[Test]
    public function persistLogsSavedRecord(): void
    {
        $post = $this->createPost(2);
        $post->setHidden(true);
        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('{type} {uid} saved', ['type' => 'Post', 'uid' => 2, 'isNew' => true, 'hidden' => true]);

        $this->subject->persist($post, true);
    }

    #[Test]
    public function activateUnhidesRecordAndUpdatesIt(): void
    {
        $topic = $this->createTopic(7);
        $topic->setHidden(true);
        $this->persistenceManager->expects($this->once())->method('update')->with($topic);
        $this->persistenceManager->expects($this->never())->method('persistAll');
        $this->logger->expects($this->once())->method('info')->with('{type} {uid} activated', ['type' => 'Topic', 'uid' => 7]);

        $this->subject->activate($topic);

        self::assertFalse($topic->getHidden());
    }

    #[Test]
    public function removeDeletesTopicWithTopicRepository(): void
    {
        $topic = $this->createTopic(7);
        $this->topicRepository->expects($this->once())->method('remove')->with($topic);
        $this->postRepository->expects($this->never())->method('remove');
        $this->logger->expects($this->once())->method('info')->with('{type} {uid} deleted', ['type' => 'Topic', 'uid' => 7]);

        $this->subject->remove($topic);
    }

    #[Test]
    public function removeDeletesPostWithPostRepository(): void
    {
        $post = $this->createPost(9);
        $this->postRepository->expects($this->once())->method('remove')->with($post);
        $this->topicRepository->expects($this->never())->method('remove');
        $this->logger->expects($this->once())->method('info')->with('{type} {uid} deleted', ['type' => 'Post', 'uid' => 9]);

        $this->subject->remove($post);
    }
}
