<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Functional\Form;

use JWeiland\Pforum\Tests\Functional\Traits\FrontendRequestTrait;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Rich text elements are rendered with the partial RichTextEditor/Textarea.html,
 * which loads the assets of the editor with RichTextEditor/LoadAssets.html
 */
class RichTextEditorAssetsTest extends FunctionalTestCase
{
    use FrontendRequestTrait;

    protected array $coreExtensionsToLoad = [
        'form',
        'rte_ckeditor',
    ];

    protected array $testExtensionsToLoad = [
        'jweiland/pforum',
    ];

    protected array $configurationToUseInTestInstance = [
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
    public function topicFormLoadsAssetsOfRichTextEditor(): void
    {
        $content = (string)$this->request('Topic', 'new', ['forum' => '1'])->getBody();

        self::assertMatchesRegularExpression('/<textarea[^>]*data-pforum-rich-text="/', $content);
        self::assertStringContainsString('@jweiland/pforum/rich-text-editor.js', $content);
        self::assertMatchesRegularExpression('/<link[^>]*rte_ckeditor\/Resources\/Public\/Css\/editor\.css/', $content);
        self::assertMatchesRegularExpression('/<link[^>]*pforum\/Resources\/Public\/Css\/RichTextEditor\.css/', $content);
    }
}
