<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Functional\Backend;

use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\Event\PageContentPreviewRenderingEvent;
use TYPO3\CMS\Backend\View\PageLayoutContext;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Domain\Persistence\RecordIdentityMap;
use TYPO3\CMS\Core\Domain\RecordFactory;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Renders the preview of the Forum plugin in the page module like TYPO3
 * does: the event PageContentPreviewRenderingEvent is dispatched to
 * AddPluginPreviewDataEventListener and to the Fluid based preview of
 * TYPO3, which renders the template of page TSconfig.
 */
class PluginPreviewTest extends FunctionalTestCase
{
    private const FLEX_FORM = '<?xml version="1.0" encoding="utf-8" standalone="yes" ?><T3FlexForms><data><sheet index="sDEF">'
        . '<language index="lDEF">'
        . '<field index="settings.pidOfDetailPage"><value index="vDEF">%d</value></field>'
        . '</language></sheet></data></T3FlexForms>';

    protected array $testExtensionsToLoad = [
        'jweiland/pforum',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Forum.csv');
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->create('default');
    }

    #[Test]
    public function pageTsConfigContainsPreviewTemplate(): void
    {
        $previewTemplates = BackendUtility::getPagesTSconfig(1)['mod.']['web_layout.']['tt_content.']['preview.'] ?? [];

        self::assertSame(
            'EXT:pforum/Resources/Private/Backend/PluginPreview/Forum.html',
            $previewTemplates['pforum_forum'] ?? '',
        );
    }

    #[Test]
    public function previewShowsTranslatedTitleAndDescriptionOfPlugin(): void
    {
        $preview = $this->renderPreview();

        self::assertStringContainsString('<strong>Forum</strong>', $preview);
        self::assertStringContainsString('Shows a list of forum entries.', $preview);
        self::assertStringNotContainsString('<table', $preview);
    }

    #[Test]
    public function previewShowsTitleOfDetailPage(): void
    {
        $preview = $this->renderPreview(sprintf(self::FLEX_FORM, 2));

        self::assertStringContainsString('PID of detail page', $preview);
        self::assertStringContainsString('Forum [2]', $preview);
    }

    private function renderPreview(string $flexForm = ''): string
    {
        $connection = $this->getConnectionPool()->getConnectionForTable('tt_content');
        $connection->insert('tt_content', ['pid' => 1, 'CType' => 'pforum_forum', 'pi_flexform' => $flexForm]);
        $record = BackendUtility::getRecord('tt_content', (int)$connection->lastInsertId());
        self::assertIsArray($record);

        $context = $this->createMock(PageLayoutContext::class);
        $context->method('getCurrentRequest')->willReturn(
            (new ServerRequest('https://www.example.com/typo3/'))
                ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE),
        );
        $context->method('getRecordIdentityMap')->willReturn(new RecordIdentityMap());

        $event = $this->get(EventDispatcherInterface::class)->dispatch(
            new PageContentPreviewRenderingEvent(
                'tt_content',
                'pforum_forum',
                $this->get(RecordFactory::class)->createFromDatabaseRow('tt_content', $record),
                $context,
            ),
        );

        return (string)$event->getPreviewContent();
    }
}
