<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\EventListener;

use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\Event\PageContentPreviewRenderingEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools;
use TYPO3\CMS\Core\Domain\RecordInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;

/**
 * Renders the preview of the Forum plugin in the page module. The preview
 * is the Fluid template of page TSconfig
 * "mod.web_layout.tt_content.preview.pforum_forum". Besides "record", the
 * template gets the variable "pforum" with:
 *
 * - settings: the settings of the FlexForm
 * - detailPageTitle: the title of the detail page. The relations of a
 *   FlexForm are not resolved by TYPO3.
 *
 * Without a template, the preview of TYPO3 is rendered.
 */
#[AsEventListener(
    identifier: 'pforum/add-plugin-preview-data',
    before: 'typo3-backend/fluid-preview/content',
)]
final readonly class AddPluginPreviewDataEventListener
{
    private const TABLE = 'tt_content';

    private const CTYPE = 'pforum_forum';

    public function __construct(
        private FlexFormTools $flexFormTools,
        private ViewFactoryInterface $viewFactory,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(PageContentPreviewRenderingEvent $event): void
    {
        if ($event->getTable() !== self::TABLE || $event->getRecordType() !== self::CTYPE) {
            return;
        }

        $record = $event->getRecord();
        $templateFile = $this->getTemplateFile($record->getPid());
        if ($templateFile === '') {
            return;
        }

        $settings = $this->getSettings($this->getFlexForm($record));
        try {
            $preview = $this->viewFactory
                ->create(new ViewFactoryData(
                    templatePathAndFilename: $templateFile,
                    request: $event->getPageLayoutContext()->getCurrentRequest(),
                ))
                ->assignMultiple([
                    'record' => $record,
                    'pforum' => [
                        'settings' => $settings,
                        'detailPageTitle' => $this->getPageTitle($settings['pidOfDetailPage'] ?? 0),
                    ],
                ])
                ->render();
        } catch (\Throwable $exception) {
            // The preview of TYPO3 is rendered instead
            $this->logger->warning('The preview of pforum plugin {uid} can not be rendered', [
                'uid' => $record->getUid(),
                'exception' => $exception,
            ]);

            return;
        }

        $event->setPreviewContent($preview);
    }

    private function getTemplateFile(int $pid): string
    {
        $templateFile = BackendUtility::getPagesTSconfig($pid)['mod.']['web_layout.'][self::TABLE . '.']['preview.'][self::CTYPE] ?? '';

        return is_string($templateFile) && $templateFile !== '' ? GeneralUtility::getFileAbsFileName($templateFile) : '';
    }

    private function getFlexForm(RecordInterface $record): string
    {
        $rawRecord = $record->getRawRecord();
        $flexForm = $rawRecord?->has('pi_flexform') ? $rawRecord->get('pi_flexform') : '';

        return is_string($flexForm) ? $flexForm : '';
    }

    /**
     * @return array<string, mixed>
     */
    private function getSettings(string $flexForm): array
    {
        $settings = $flexForm === '' ? [] : $this->flexFormTools->convertFlexFormContentToArray($flexForm)['settings'] ?? [];

        return is_array($settings) ? $settings : [];
    }

    private function getPageTitle(mixed $uid): string
    {
        $page = is_numeric($uid) && (int)$uid > 0 ? BackendUtility::getRecord('pages', (int)$uid, 'title') : null;

        return (string)($page['title'] ?? '');
    }
}
