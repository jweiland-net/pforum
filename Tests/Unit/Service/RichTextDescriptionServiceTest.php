<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Unit\Service;

use JWeiland\Pforum\Converter\RteContentConverter;
use JWeiland\Pforum\Domain\Model\Post;
use JWeiland\Pforum\Domain\Model\Topic;
use JWeiland\Pforum\Event\ModifyRichTextSanitizerConfigurationEvent;
use JWeiland\Pforum\Sanitizer\RichTextSanitizer;
use JWeiland\Pforum\Sanitizer\RichTextSanitizerConfiguration;
use JWeiland\Pforum\Service\RichTextDescriptionService;
use JWeiland\Pforum\Service\RichTextEditorOptions;
use JWeiland\Pforum\Service\RichTextService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 */
class RichTextDescriptionServiceTest extends UnitTestCase
{
    private PackageManager&MockObject $packageManager;

    private RichTextService&MockObject $richTextService;

    private RichTextSanitizer&MockObject $richTextSanitizer;

    private RichTextEditorOptions&MockObject $richTextEditorOptions;

    private RteContentConverter&MockObject $rteContentConverter;

    private RichTextDescriptionService $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->packageManager = $this->createMock(PackageManager::class);
        $this->richTextService = $this->createMock(RichTextService::class);
        $this->richTextSanitizer = $this->createMock(RichTextSanitizer::class);
        $this->richTextEditorOptions = $this->createMock(RichTextEditorOptions::class);
        $this->rteContentConverter = $this->createMock(RteContentConverter::class);

        $this->subject = new RichTextDescriptionService(
            $this->packageManager,
            $this->richTextService,
            $this->richTextSanitizer,
            $this->richTextEditorOptions,
            $this->rteContentConverter,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function richTextSettings(bool|string|int $enable = true, string $elements = 'description'): array
    {
        return ['new' => ['richText' => ['enable' => $enable, 'elements' => $elements, 'translationsPath' => 'EXT:foo/']]];
    }

    /**
     * @return array<string, array{array<string, mixed>, bool, bool}>
     */
    public static function isEnabledDataProvider(): array
    {
        return [
            'enabled' => [['new' => ['richText' => ['enable' => '1', 'elements' => 'description']]], true, true],
            'enabled with several elements' => [['new' => ['richText' => ['enable' => 1, 'elements' => 'title, description']]], true, true],
            'disabled' => [['new' => ['richText' => ['enable' => '0', 'elements' => 'description']]], true, false],
            'empty enable' => [['new' => ['richText' => ['enable' => '', 'elements' => 'description']]], true, false],
            'description is not an element' => [['new' => ['richText' => ['enable' => '1', 'elements' => 'title']]], true, false],
            'no elements' => [['new' => ['richText' => ['enable' => '1']]], true, false],
            'similar element name' => [['new' => ['richText' => ['enable' => '1', 'elements' => 'descriptions']]], true, false],
            'rte_ckeditor not active' => [['new' => ['richText' => ['enable' => '1', 'elements' => 'description']]], false, false],
            'no settings' => [[], true, false],
            'richText is no array' => [['new' => ['richText' => '1']], true, false],
            'new is no array' => [['new' => 'foo'], true, false],
        ];
    }

    /**
     * @param array<string, mixed> $settings
     */
    #[Test]
    #[DataProvider('isEnabledDataProvider')]
    public function isEnabledEvaluatesSettingsAndRteCkeditor(array $settings, bool $rteCkeditorActive, bool $expected): void
    {
        $this->packageManager->method('isPackageActive')->with('rte_ckeditor')->willReturn($rteCkeditorActive);

        self::assertSame($expected, $this->subject->isEnabled($settings));
    }

    #[Test]
    public function prepareForPersistenceConvertsPlainTextToHtmlIfDisabled(): void
    {
        $this->packageManager->method('isPackageActive')->willReturn(true);
        $this->richTextService->expects($this->never())->method('prepareForPersistence');
        $this->rteContentConverter
            ->expects($this->once())
            ->method('convertPlainTextToHtml')
            ->with("foo\nbar")
            ->willReturn('<p>foo<br>bar</p>');
        $topic = new Topic();
        $topic->setDescription("foo\nbar");

        $this->subject->prepareForPersistence($topic, []);

        self::assertSame('<p>foo<br>bar</p>', $topic->getDescription());
    }

    #[Test]
    public function prepareForPersistenceConvertsPlainTextIfRteCkeditorIsMissing(): void
    {
        $this->packageManager->method('isPackageActive')->with('rte_ckeditor')->willReturn(false);
        $this->richTextService->expects($this->never())->method('prepareForPersistence');
        $this->rteContentConverter->method('convertPlainTextToHtml')->willReturn('<p>x</p>');
        $post = new Post();
        $post->setDescription('x');

        $this->subject->prepareForPersistence($post, $this->richTextSettings());

        self::assertSame('<p>x</p>', $post->getDescription());
    }

    #[Test]
    public function prepareForPersistenceUsesRichTextServiceWithTableOfTopic(): void
    {
        $this->packageManager->method('isPackageActive')->willReturn(true);
        $this->rteContentConverter->expects($this->never())->method('convertPlainTextToHtml');
        $this->richTextService
            ->expects($this->once())
            ->method('prepareForPersistence')
            ->with('<p>a</p>', 'tx_pforum_domain_model_topic', 'description', 11, 3)
            ->willReturn('<p>sanitized</p>');
        $topic = new Topic();
        $topic->_setProperty('uid', 11);
        $topic->setPid(3);
        $topic->setDescription('<p>a</p>');

        $this->subject->prepareForPersistence($topic, $this->richTextSettings());

        self::assertSame('<p>sanitized</p>', $topic->getDescription());
    }

    #[Test]
    public function prepareForPersistenceOfNewPostUsesUidAndPidZero(): void
    {
        $this->packageManager->method('isPackageActive')->willReturn(true);
        $this->richTextService
            ->expects($this->once())
            ->method('prepareForPersistence')
            ->with('b', 'tx_pforum_domain_model_post', 'description', 0, 0)
            ->willReturn('c');
        $post = new Post();
        $post->setDescription('b');

        $this->subject->prepareForPersistence($post, $this->richTextSettings());

        self::assertSame('c', $post->getDescription());
    }

    #[Test]
    public function getFormVariablesOfNewRecordWithoutEditorHasNoPlainText(): void
    {
        $this->packageManager->method('isPackageActive')->willReturn(true);
        $this->rteContentConverter->expects($this->never())->method('convertHtmlToPlainText');

        $variables = $this->subject->getFormVariables(new Topic(), [], self::createStub(ServerRequestInterface::class));

        self::assertSame(['richTextEditor' => ['enabled' => false], 'descriptionPlainText' => null], $variables);
    }

    #[Test]
    public function getFormVariablesOfExistingRecordWithoutEditorContainsPlainText(): void
    {
        $this->packageManager->method('isPackageActive')->willReturn(false);
        $this->rteContentConverter
            ->expects($this->once())
            ->method('convertHtmlToPlainText')
            ->with('<p>foo</p>')
            ->willReturn('foo');
        $this->richTextEditorOptions->expects($this->never())->method('getEditorOptions');
        $post = new Post();
        $post->_setProperty('uid', 4);
        $post->setDescription('<p>foo</p>');

        $variables = $this->subject->getFormVariables($post, $this->richTextSettings(), self::createStub(ServerRequestInterface::class));

        self::assertSame(['richTextEditor' => ['enabled' => false], 'descriptionPlainText' => 'foo'], $variables);
    }

    #[Test]
    public function getFormVariablesWithEditorContainsEditorOptionsAsJson(): void
    {
        $this->packageManager->method('isPackageActive')->willReturn(true);
        $request = self::createStub(ServerRequestInterface::class);
        $configuration = RichTextSanitizerConfiguration::fromEvent(
            new ModifyRichTextSanitizerConfigurationEvent(['strong'], [], [], []),
        );
        $this->richTextSanitizer
            ->expects($this->once())
            ->method('getConfiguration')
            ->with('tx_pforum_domain_model_post', 'description')
            ->willReturn($configuration);
        $this->richTextEditorOptions
            ->expects($this->once())
            ->method('getEditorOptions')
            ->with($request, $configuration, 'EXT:foo/')
            ->willReturn(['language' => 'de', 'allowedTags' => ['p', 'strong']]);
        $this->rteContentConverter->expects($this->never())->method('convertHtmlToPlainText');

        $variables = $this->subject->getFormVariables(new Post(), $this->richTextSettings(), $request);

        self::assertSame(
            ['richTextEditor' => ['enabled' => true, 'options' => '{"language":"de","allowedTags":["p","strong"]}']],
            $variables,
        );
    }
}
