<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Functional\ViewHelpers;

use JWeiland\Pforum\Event\ModifyRichTextSanitizerConfigurationEvent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\EventDispatcher\ListenerProvider;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContextFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use TYPO3Fluid\Fluid\Core\ViewHelper\Exception;
use TYPO3Fluid\Fluid\View\TemplateView;

/**
 * Functional test
 */
class FormatRichTextViewHelperTest extends FunctionalTestCase
{
    private const RICH_TEXT = '<h2 data-list-item-id="e1">Program</h2>'
        . '<p>Start at <strong>10 o\'clock</strong></p>'
        . '<script>alert("xss")</script>'
        . '<ul><li data-list-item-id="e2">Music</li><li data-list-item-id="e3">Food</li></ul>'
        . '<p><a href="https://example.com" onclick="alert(1)">Tickets</a></p>';

    protected array $testExtensionsToLoad = [
        'jweiland/pforum',
    ];

    #[Test]
    public function renderReturnsSanitizedHtml(): void
    {
        $output = $this->renderViewHelper(self::RICH_TEXT, 'html');

        self::assertStringContainsString('<h2>Program</h2>', $output);
        self::assertStringContainsString('<strong>10 o\'clock</strong>', $output);
        self::assertStringContainsString('<ul><li>Music</li><li>Food</li></ul>', $output);
        self::assertStringContainsString('>Tickets</a>', $output);
        self::assertStringNotContainsString('data-list-item-id', $output);
        self::assertStringNotContainsString('<script', $output);
        self::assertStringNotContainsString('alert', $output);
        self::assertStringNotContainsString('onclick', $output);
    }

    #[Test]
    public function renderReturnsPlainTextWithLineBreaks(): void
    {
        $output = $this->renderViewHelper(self::RICH_TEXT, 'plaintext');

        self::assertSame(
            "Program\n\nStart at 10 o'clock\n\nMusic\nFood\n\nTickets",
            $output,
        );
    }

    #[Test]
    public function renderUsesSanitizerConfigurationOfTableAndColumn(): void
    {
        $tablesAndColumns = [];
        $this->get(ListenerProvider::class)->addListener(
            ModifyRichTextSanitizerConfigurationEvent::class,
            'pforum-test-listener',
        );
        $this->getContainer()->set(
            'pforum-test-listener',
            static function (ModifyRichTextSanitizerConfigurationEvent $event) use (&$tablesAndColumns): void {
                $tablesAndColumns[] = $event->getTable() . '.' . $event->getColumn();
            },
        );

        $this->renderViewHelper('<p>Text</p>', 'html');

        self::assertSame(
            ['tx_pforum_domain_model_post.description'],
            $tablesAndColumns,
        );
    }

    #[Test]
    public function renderWithEmptyValueReturnsEmptyString(): void
    {
        self::assertSame(
            '',
            $this->renderViewHelper('   ', 'html'),
        );
    }

    #[Test]
    public function renderWithInvalidFormatThrowsException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionCode(1763890802);

        $this->renderViewHelper('<p>Text</p>', 'pdf');
    }

    public static function mailTemplateDataProvider(): array
    {
        return [
            'HTML part' => ['PforumNewPost.html'],
            'Text part' => ['PforumNewPost.txt'],
        ];
    }

    #[Test]
    #[DataProvider('mailTemplateDataProvider')]
    public function mailTemplateUsesViewHelperForRichTextElements(string $templateFile): void
    {
        $templateSource = (string)file_get_contents(
            __DIR__ . '/../../../Resources/Private/Templates/Email/' . $templateFile,
        );
        $context = $this->get(RenderingContextFactory::class)->create();

        // Throws an exception on invalid syntax or unknown ViewHelpers
        $context->getTemplateParser()->parse($templateSource);

        self::assertStringContainsString('<pf:formatRichText value="{post.description}"', $templateSource);
    }

    #[Test]
    public function renderConvertsPlainTextOfFormerVersions(): void
    {
        self::assertSame('<p>First line<br>Second line &amp; more</p>', $this->renderViewHelper("First line\nSecond line & more", 'html'));
    }

    private function renderViewHelper(string $value, string $format): string
    {
        $context = $this->get(RenderingContextFactory::class)->create();
        $context->getVariableProvider()->add('value', $value);
        $context->getVariableProvider()->add('format', $format);
        $context->getTemplatePaths()->setTemplateSource(
            '{namespace pf=JWeiland\\Pforum\\ViewHelpers}'
            . '<pf:formatRichText value="{value}" table="tx_pforum_domain_model_post" column="description" format="{format}"/>',
        );

        return trim((new TemplateView($context))->render());
    }
}
