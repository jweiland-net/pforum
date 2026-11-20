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
 * The templates of the Forum plugin render the markup of Bootstrap 5
 */
class ForumMarkupTest extends FunctionalTestCase
{
    use FrontendRequestTrait;

    private const ADMIN = 7;

    protected array $testExtensionsToLoad = [
        'jweiland/pforum',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Forum.csv');
        $this->setUpForumSite(['EXT:pforum/Tests/Functional/Fixtures/Frontend/Pagination.typoscript']);
    }

    #[Test]
    public function showForumRendersPaginationOfBootstrap(): void
    {
        $content = (string)$this->request('Forum', 'show', ['forum' => '1'], [], self::ADMIN)->getBody();

        self::assertStringContainsString('<ul class="pagination">', $content);
        self::assertMatchesRegularExpression('/<li class="page-item next">\s*<a class="page-link"[^>]*>\s*Next\s*<\/a>/', $content);
        self::assertStringContainsString('<li class="page-item active" aria-current="page">', $content);
        self::assertStringNotContainsString('f3-widget-paginator', $content);
    }

    #[Test]
    public function showForumRendersBadgeAndActivationLinkOfHiddenTopicForAdmin(): void
    {
        $content = (string)$this->request('Forum', 'show', ['forum' => '1', 'currentPage' => '3'], [], self::ADMIN)->getBody();

        self::assertStringContainsString('<span class="badge text-bg-secondary">Hidden</span>', $content);
        self::assertMatchesRegularExpression('/<a class="btn btn-sm btn-outline-primary"[^>]*confirmActivation/', $content);
    }

    #[Test]
    public function showForumRendersButtonToCreateTopic(): void
    {
        $content = (string)$this->request('Forum', 'show', ['forum' => '1'])->getBody();

        self::assertMatchesRegularExpression('/<a class="btn btn-primary"[^>]*>\s*Create new Topic\s*<\/a>/', $content);
        self::assertStringNotContainsString('badge', $content);
    }

    #[Test]
    public function newTopicRendersFormOfBootstrap(): void
    {
        $content = (string)$this->request('Topic', 'new', ['forum' => '1'])->getBody();

        self::assertMatchesRegularExpression('/<label for="topic-title" class="form-label">/', $content);
        self::assertMatchesRegularExpression('/<input[^>]*id="topic-title"[^>]*class="form-control"/', $content);
        self::assertMatchesRegularExpression('/<textarea[^>]*id="topic-description"[^>]*class="form-control"/', $content);
        self::assertMatchesRegularExpression('/<input(?=[^>]*type="submit")(?=[^>]*class="btn btn-primary")/', $content);
    }
}
