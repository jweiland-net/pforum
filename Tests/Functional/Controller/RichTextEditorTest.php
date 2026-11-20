<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Functional\Controller;

use JWeiland\Pforum\Security\AnonymousAccessTokenService;
use JWeiland\Pforum\Tests\Functional\Traits\FrontendRequestTrait;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * The description of topics and posts with the rich text editor
 */
class RichTextEditorTest extends FunctionalTestCase
{
    use FrontendRequestTrait;

    protected array $coreExtensionsToLoad = [
        'rte_ckeditor',
    ];

    protected array $testExtensionsToLoad = [
        'jweiland/pforum',
    ];

    protected array $configurationToUseInTestInstance = [
        'MAIL' => [
            'transport' => 'null',
        ],
        'EXTENSIONS' => [
            'pforum' => [
                'emailFromAddress' => 'forum@example.com',
                'emailFromName' => 'Forum',
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Forum.csv');
        $this->setUpForumSite(['EXT:pforum/Tests/Functional/Fixtures/Frontend/RichTextEditor.typoscript']);
    }

    #[Test]
    public function newActionRendersTextareaWithEditorAndLoadsAssets(): void
    {
        $content = (string)$this->request('Topic', 'new', ['forum' => '1'])->getBody();

        self::assertMatchesRegularExpression('/<textarea[^>]*data-pforum-rich-text="\{[^"]*allowedTags/', $content);
        self::assertStringContainsString('@jweiland/pforum/rich-text-editor.js', $content);
        self::assertMatchesRegularExpression('/<link[^>]*rte_ckeditor\/Resources\/Public\/Css\/editor\.css/', $content);
        self::assertMatchesRegularExpression('/<link[^>]*pforum\/Resources\/Public\/Css\/RichTextEditor\.css/', $content);
    }

    #[Test]
    public function createStoresSanitizedRichText(): void
    {
        $this->submitForm('Topic', 'create', [], [
            'forum' => '1',
            'topic' => [
                'title' => 'Rich topic',
                'description' => '<p onclick="alert(1)">Hello <strong>world</strong></p><script>alert(1)</script>',
                'anonymousUser' => ['name' => 'John', 'username' => 'john', 'email' => 'john@example.com'],
            ],
        ]);

        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('tx_pforum_domain_model_topic');
        $queryBuilder->getRestrictions()->removeAll();
        $description = $queryBuilder->select('description')->from('tx_pforum_domain_model_topic')
            ->where($queryBuilder->expr()->eq('title', $queryBuilder->createNamedParameter('Rich topic')))
            ->executeQuery()->fetchOne();

        self::assertSame('<p>Hello <strong>world</strong></p>', $description);
    }

    #[Test]
    public function previewShowsRenderedRichText(): void
    {
        $content = (string)$this->request('Topic', 'edit', [
            'topic' => '2',
            'isPreview' => '1',
            'token' => $this->get(AnonymousAccessTokenService::class)->generateToken('Topic', 2),
        ])->getBody();

        self::assertMatchesRegularExpression('/<div class="card-text"><p>First line<br\s?\/?>Second line<\/p><\/div>/', $content);
    }
}
