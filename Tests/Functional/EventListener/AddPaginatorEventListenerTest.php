<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Functional\EventListener;

use JWeiland\Pforum\Domain\Model\Forum;
use JWeiland\Pforum\Domain\Model\Topic;
use JWeiland\Pforum\Domain\Repository\ForumRepository;
use JWeiland\Pforum\Domain\Repository\PostRepository;
use JWeiland\Pforum\Domain\Repository\TopicRepository;
use JWeiland\Pforum\Event\PostProcessFluidVariablesEvent;
use JWeiland\Pforum\EventListener\AddPaginatorEventListener;
use JWeiland\Pforum\Tests\Functional\Traits\ExtbaseRequestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Pagination\SimplePagination;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Pagination\QueryResultPaginator;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 */
class AddPaginatorEventListenerTest extends FunctionalTestCase
{
    use ExtbaseRequestTrait;

    protected array $testExtensionsToLoad = [
        'jweiland/pforum',
    ];

    private AddPaginatorEventListener $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Forum.csv');
        $this->createExtbaseRequest();
        $this->subject = $this->get(AddPaginatorEventListener::class);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function untouchedActionsProvider(): array
    {
        return [
            'list of forums' => ['Forum', 'list'],
            'new topic' => ['Topic', 'new'],
            'edit topic' => ['Topic', 'edit'],
            'show of posts' => ['Post', 'show'],
            'unknown controller' => ['Administration', 'show'],
        ];
    }

    #[Test]
    public function forumShowPaginatesTopics(): void
    {
        $event = $this->dispatch('Forum', 'show', ['topics' => $this->findTopics()], ['pageBrowser' => ['itemsPerPage' => '2']]);

        $variables = $event->getFluidVariables();
        self::assertInstanceOf(QueryResultPaginator::class, $variables['paginator']);
        self::assertInstanceOf(SimplePagination::class, $variables['pagination']);
        self::assertSame('show', $variables['actionName']);
        self::assertSame(2, $variables['paginator']->getNumberOfPages());
        self::assertSame(1, $variables['paginator']->getCurrentPageNumber());
        self::assertSame([1, 2], $this->getUids($variables['topics']));
    }

    #[Test]
    public function forumShowShowsRequestedPage(): void
    {
        $event = $this->dispatch(
            'Forum',
            'show',
            ['topics' => $this->findTopics()],
            ['pageBrowser' => ['itemsPerPage' => '2']],
            ['currentPage' => '2'],
        );

        $variables = $event->getFluidVariables();
        self::assertSame(2, $variables['paginator']->getCurrentPageNumber());
        self::assertSame([3], $this->getUids($variables['topics']));
    }

    #[Test]
    public function topicShowPaginatesPosts(): void
    {
        $posts = $this->get(PostRepository::class)->findByTopic($this->getTopic(1), true);

        $event = $this->dispatch('Topic', 'show', ['posts' => $posts], ['pageBrowser' => ['itemsPerPage' => '1']]);

        $variables = $event->getFluidVariables();
        self::assertSame('show', $variables['actionName']);
        self::assertSame(2, $variables['paginator']->getNumberOfPages());
        self::assertCount(1, $variables['posts']);
        self::assertArrayNotHasKey('topics', $variables);
    }

    #[Test]
    public function itemsPerPageOfSettingsIsUsed(): void
    {
        $event = $this->dispatch('Forum', 'show', ['topics' => $this->findTopics()], ['pageBrowser' => ['itemsPerPage' => '1']]);

        self::assertSame(3, $event->getFluidVariables()['paginator']->getNumberOfPages());
        self::assertCount(1, $event->getFluidVariables()['topics']);
    }

    #[Test]
    #[DataProvider('invalidItemsPerPageProvider')]
    public function defaultItemsPerPageIsUsedForInvalidSettings(array $settings): void
    {
        $event = $this->dispatch('Forum', 'show', ['topics' => $this->findTopics()], $settings);

        self::assertSame(1, $event->getFluidVariables()['paginator']->getNumberOfPages());
        self::assertCount(3, $event->getFluidVariables()['topics']);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidItemsPerPageProvider(): array
    {
        return [
            'without setting' => [[]],
            'zero' => [['pageBrowser' => ['itemsPerPage' => '0']]],
            'negative' => [['pageBrowser' => ['itemsPerPage' => '-5']]],
            'not numeric' => [['pageBrowser' => ['itemsPerPage' => 'many']]],
        ];
    }

    #[Test]
    public function currentPageBelowOneIsCorrectedToFirstPage(): void
    {
        $event = $this->dispatch(
            'Forum',
            'show',
            ['topics' => $this->findTopics()],
            ['pageBrowser' => ['itemsPerPage' => '2']],
            ['currentPage' => '-3'],
        );

        self::assertSame(1, $event->getFluidVariables()['paginator']->getCurrentPageNumber());
    }

    #[Test]
    #[DataProvider('untouchedActionsProvider')]
    public function otherActionsKeepFluidVariables(string $controllerName, string $actionName): void
    {
        $topics = $this->findTopics();

        $event = $this->dispatch($controllerName, $actionName, ['topics' => $topics, 'posts' => $topics]);

        self::assertSame(['topics' => $topics, 'posts' => $topics], $event->getFluidVariables());
    }

    #[Test]
    public function variablesWithoutQueryResultAreKept(): void
    {
        $event = $this->dispatch('Forum', 'show', ['topics' => ['a', 'b']]);
        self::assertSame(['topics' => ['a', 'b']], $event->getFluidVariables());

        $event = $this->dispatch('Forum', 'show', ['forum' => 'Forum']);
        self::assertSame(['forum' => 'Forum'], $event->getFluidVariables());
    }

    /**
     * @param array<string, mixed> $variables
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $arguments
     */
    private function dispatch(
        string $controllerName,
        string $actionName,
        array $variables,
        array $settings = [],
        array $arguments = [],
    ): PostProcessFluidVariablesEvent {
        $request = $this->createExtbaseRequest(10, $controllerName, $actionName);
        $request = $this->withArguments($request, $arguments);
        $event = new PostProcessFluidVariablesEvent($request, $settings, $variables);

        ($this->subject)($event);

        return $event;
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function withArguments(Request $request, array $arguments): Request
    {
        foreach ($arguments as $name => $value) {
            $request = $request->withArgument($name, $value);
        }

        return $request;
    }

    private function findTopics(): object
    {
        return $this->get(TopicRepository::class)->findByForum($this->getForum(1), true);
    }

    private function getForum(int $uid): Forum
    {
        $forum = $this->get(ForumRepository::class)->findByIdentifier($uid);
        self::assertInstanceOf(Forum::class, $forum);

        return $forum;
    }

    private function getTopic(int $uid): Topic
    {
        $topic = $this->get(TopicRepository::class)->findHiddenObject($uid);
        self::assertInstanceOf(Topic::class, $topic);

        return $topic;
    }

    /**
     * @param iterable<Topic> $topics
     * @return int[]
     */
    private function getUids(iterable $topics): array
    {
        $uids = [];
        foreach ($topics as $topic) {
            $uids[] = (int)$topic->getUid();
        }

        return $uids;
    }
}
