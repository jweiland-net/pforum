<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Unit\Service;

use JWeiland\Pforum\Event\ModifyRichTextSanitizerConfigurationEvent;
use JWeiland\Pforum\Sanitizer\RichTextSanitizerConfiguration;
use JWeiland\Pforum\Service\RichTextEditorOptions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Localization\Locale;
use TYPO3\CMS\Core\Localization\Locales;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\SystemResource\Exception\SystemResourceException;
use TYPO3\CMS\Core\SystemResource\Publishing\SystemResourcePublisherInterface;
use TYPO3\CMS\Core\SystemResource\SystemResourceFactory;
use TYPO3\CMS\Core\SystemResource\Type\PublicResourceInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 */
class RichTextEditorOptionsTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    private SystemResourceFactory&MockObject $systemResourceFactory;

    private SystemResourcePublisherInterface&MockObject $resourcePublisher;

    private LoggerInterface&MockObject $logger;

    private RichTextEditorOptions $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->systemResourceFactory = $this->createMock(SystemResourceFactory::class);
        $this->resourcePublisher = $this->createMock(SystemResourcePublisherInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subject = new RichTextEditorOptions($this->systemResourceFactory, $this->resourcePublisher, $this->logger);
    }

    /**
     * LocalizationUtility::translate() is static and creates its collaborators with GeneralUtility::makeInstance()
     */
    private function mockTranslation(?string $translation): void
    {
        $locales = $this->createMock(Locales::class);
        $locales->method('createLocaleFromRequest')->willReturn(new Locale('en'));
        GeneralUtility::setSingletonInstance(Locales::class, $locales);

        $languageService = $this->createMock(LanguageService::class);
        $languageService->method('translate')->willReturn($translation);
        $languageServiceFactory = $this->createMock(LanguageServiceFactory::class);
        $languageServiceFactory->method('create')->willReturn($languageService);
        GeneralUtility::addInstance(LanguageServiceFactory::class, $languageServiceFactory);
    }

    private function createRequest(?string $locale): ServerRequestInterface
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $siteLanguage = $locale === null ? null : new SiteLanguage(1, $locale, new Uri('/'), []);
        $request->method('getAttribute')->willReturnCallback(
            static fn(string $name, mixed $default = null): mixed => $name === 'language' ? $siteLanguage : $default,
        );

        return $request;
    }

    private function createConfiguration(): RichTextSanitizerConfiguration
    {
        return RichTextSanitizerConfiguration::fromEvent(
            new ModifyRichTextSanitizerConfigurationEvent(['strong', 'h2'], [], [], []),
        );
    }

    #[Test]
    public function getEditorOptionsReturnsAllowedTagsAndTranslatedLabel(): void
    {
        $this->mockTranslation('In neuem Tab oeffnen');

        $options = $this->subject->getEditorOptions($this->createRequest('en-US'), $this->createConfiguration(), 'EXT:foo/');

        self::assertSame(['p', 'strong', 'h2'], $options['allowedTags']);
        self::assertSame(['openInNewTab' => 'In neuem Tab oeffnen'], $options['labels']);
    }

    #[Test]
    public function missingTranslationFallsBackToEnglishLabel(): void
    {
        $this->mockTranslation(null);

        $options = $this->subject->getEditorOptions($this->createRequest('en-US'), $this->createConfiguration(), '');

        self::assertSame(['openInNewTab' => 'Open in a new tab'], $options['labels']);
    }

    #[Test]
    public function languageCodeDefaultsToEnglishWithoutSiteLanguage(): void
    {
        $this->mockTranslation(null);
        $this->systemResourceFactory->expects($this->never())->method('createPublicResource');

        $options = $this->subject->getEditorOptions($this->createRequest(null), $this->createConfiguration(), 'EXT:foo/translations/');

        self::assertSame('en', $options['language']);
        self::assertSame('', $options['translationUrl']);
    }

    #[Test]
    public function translationUrlOfEnglishIsEmpty(): void
    {
        $this->mockTranslation(null);
        $this->systemResourceFactory->expects($this->never())->method('createPublicResource');
        $this->resourcePublisher->expects($this->never())->method('generateUri');

        $options = $this->subject->getEditorOptions($this->createRequest('en-GB'), $this->createConfiguration(), 'EXT:foo/translations/');

        self::assertSame('en', $options['language']);
        self::assertSame('', $options['translationUrl']);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function languageDataProvider(): array
    {
        return [
            'german' => ['de-DE', 'de', 'EXT:foo/translations/de.js'],
            'underscore locale' => ['fr_FR', 'fr', 'EXT:foo/translations/fr.js'],
            'three letter code' => ['fil-PH', 'fil', 'EXT:foo/translations/fil.js'],
            'language only' => ['nl', 'nl', 'EXT:foo/translations/nl.js'],
        ];
    }

    #[Test]
    #[DataProvider('languageDataProvider')]
    public function translationUrlIsGeneratedForLanguageOfSite(string $locale, string $languageCode, string $expectedFile): void
    {
        $this->mockTranslation(null);
        $request = $this->createRequest($locale);
        $resource = self::createStub(PublicResourceInterface::class);
        $uri = $this->createMock(UriInterface::class);
        $uri->method('__toString')->willReturn('/typo3conf/ext/foo/translations/' . $languageCode . '.js');
        $this->systemResourceFactory
            ->expects($this->once())
            ->method('createPublicResource')
            ->with($expectedFile)
            ->willReturn($resource);
        $this->resourcePublisher->expects($this->once())->method('generateUri')->with($resource, $request)->willReturn($uri);

        $options = $this->subject->getEditorOptions($request, $this->createConfiguration(), 'EXT:foo/translations/');

        self::assertSame($languageCode, $options['language']);
        self::assertSame('/typo3conf/ext/foo/translations/' . $languageCode . '.js', $options['translationUrl']);
    }

    #[Test]
    public function translationsPathIsTrimmedAndTrailingSlashIsNormalized(): void
    {
        $this->mockTranslation(null);
        $this->systemResourceFactory
            ->expects($this->once())
            ->method('createPublicResource')
            ->with('EXT:foo/translations/de.js')
            ->willReturn(self::createStub(PublicResourceInterface::class));
        $this->resourcePublisher->method('generateUri')->willReturn(new Uri('/de.js'));

        $this->subject->getEditorOptions($this->createRequest('de-DE'), $this->createConfiguration(), "  EXT:foo/translations// \n");
    }

    #[Test]
    public function emptyTranslationsPathResultsInEmptyTranslationUrl(): void
    {
        $this->mockTranslation(null);
        $this->systemResourceFactory->expects($this->never())->method('createPublicResource');

        $options = $this->subject->getEditorOptions($this->createRequest('de-DE'), $this->createConfiguration(), '   ');

        self::assertSame('de', $options['language']);
        self::assertSame('', $options['translationUrl']);
    }

    #[Test]
    public function missingTranslationFileResultsInEmptyTranslationUrlAndDebugLog(): void
    {
        $this->mockTranslation(null);
        $this->systemResourceFactory->method('createPublicResource')->willThrowException(new SystemResourceException('missing', 1));
        $this->logger
            ->expects($this->once())
            ->method('debug')
            ->with('No CKEditor translation "{file}" available', self::callback(
                static fn(array $context): bool => $context['file'] === 'EXT:foo/de.js' && $context['exception'] instanceof SystemResourceException,
            ));

        $options = $this->subject->getEditorOptions($this->createRequest('de-DE'), $this->createConfiguration(), 'EXT:foo');

        self::assertSame('', $options['translationUrl']);
    }
}
