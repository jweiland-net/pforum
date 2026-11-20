<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Functional\Controller;

use JWeiland\Pforum\Tests\Functional\Traits\FrontendRequestTrait;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Topics and posts of logged-in frontend users (settings.auth = 2)
 */
class FrontendUserModeTest extends FunctionalTestCase
{
    use FrontendRequestTrait;

    private const AUTHOR = 5;

    private const OTHER_USER = 6;

    protected array $testExtensionsToLoad = [
        'jweiland/pforum',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Forum.csv');
        $this->setUpForumSite(['EXT:pforum/Tests/Functional/Fixtures/Frontend/FrontendUserMode.typoscript']);
    }

    #[Test]
    public function showForumRendersCreateButtonForMemberOfUserGroup(): void
    {
        $content = (string)$this->request('Forum', 'show', ['forum' => '1'], [], self::OTHER_USER)->getBody();

        self::assertStringContainsString('Create new Topic', $content);
    }

    #[Test]
    public function showForumRendersNoCreateButtonWithoutLogin(): void
    {
        $content = (string)$this->request('Forum', 'show', ['forum' => '1'])->getBody();

        self::assertStringNotContainsString('Create new Topic', $content);
    }

    #[Test]
    public function showTopicRendersLinksToEditAndDeleteForAuthor(): void
    {
        $content = (string)$this->request('Topic', 'show', ['topic' => '1'], [], self::AUTHOR)->getBody();

        self::assertMatchesRegularExpression('/<a class="btn btn-sm btn-outline-secondary"[^>]*action%5D=edit[^>]*>\s*Edit\s*<\/a>/', $content);
        self::assertMatchesRegularExpression('/<a class="btn btn-sm btn-outline-danger"[^>]*action%5D=delete[^>]*>\s*Delete\s*<\/a>/', $content);
    }

    #[Test]
    public function showTopicRendersNoLinksToEditForOtherUser(): void
    {
        $content = (string)$this->request('Topic', 'show', ['topic' => '1'], [], self::OTHER_USER)->getBody();

        // The post of the other user gets the links, the topic of the author not
        self::assertDoesNotMatchRegularExpression('/href="[^"]*controller%5D=Topic[^"]*"[^>]*>\s*Edit/', $content);
        self::assertMatchesRegularExpression('/href="[^"]*controller%5D=Post[^"]*"[^>]*>\s*Edit/', $content);
    }

    #[Test]
    public function createStoresVisibleTopicOfFrontendUser(): void
    {
        $this->submitForm('Topic', 'create', [], [
            'forum' => '1',
            'topic' => ['title' => 'Topic of other', 'description' => 'Question'],
        ], self::OTHER_USER);

        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('tx_pforum_domain_model_topic');
        $queryBuilder->getRestrictions()->removeAll();
        $topic = $queryBuilder->select('*')->from('tx_pforum_domain_model_topic')
            ->where($queryBuilder->expr()->eq('title', $queryBuilder->createNamedParameter('Topic of other')))
            ->executeQuery()->fetchAssociative();

        self::assertIsArray($topic);
        self::assertSame(self::OTHER_USER, (int)$topic['frontend_user']);
        self::assertSame(0, (int)$topic['anonymous_user']);
        self::assertSame(0, (int)$topic['hidden']);
    }

    #[Test]
    public function createWithoutLoginStoresNoTopic(): void
    {
        $this->submitForm('Topic', 'create', [], [
            'forum' => '1',
            'topic' => ['title' => 'Topic without login', 'description' => 'Question'],
        ]);

        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('tx_pforum_domain_model_topic');
        $queryBuilder->getRestrictions()->removeAll();
        $count = $queryBuilder->count('uid')->from('tx_pforum_domain_model_topic')
            ->where($queryBuilder->expr()->eq('title', $queryBuilder->createNamedParameter('Topic without login')))
            ->executeQuery()->fetchOne();

        self::assertSame(0, (int)$count);
    }

    #[Test]
    public function updateOfTopicOfOtherUserKeepsTopic(): void
    {
        $this->submitForm('Topic', 'update', [], [
            'topic' => ['__identity' => '1', 'title' => 'Hijacked', 'description' => 'Question'],
        ], self::OTHER_USER);

        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('tx_pforum_domain_model_topic');
        $title = $queryBuilder->select('title')->from('tx_pforum_domain_model_topic')
            ->where($queryBuilder->expr()->eq('uid', 1))->executeQuery()->fetchOne();

        self::assertSame('Welcome', $title);
    }
}
