<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\EventListener;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\Event\PageContentPreviewRenderingEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Service\FlexFormService;

/**
 * Prepares the data of the preview of the Forum plugin in the page module.
 * The preview itself is the Fluid template of page TSconfig
 * "mod.web_layout.tt_content.preview.pforum_forum", rendered by TYPO3. The
 * template gets the variable "pforum" with:
 *
 * - settings: the settings of the FlexForm
 * - detailPageTitle: the title of the detail page. The relations of a
 *   FlexForm are not resolved by TYPO3.
 */
#[AsEventListener(
    identifier: 'pforum/add-plugin-preview-data',
    before: 'typo3-backend/fluid-preview/content',
)]
final readonly class AddPluginPreviewDataEventListener
{
    private const CTYPE = 'pforum_forum';

    public function __construct(
        private FlexFormService $flexFormService,
    ) {}

    public function __invoke(PageContentPreviewRenderingEvent $event): void
    {
        if ($event->getTable() !== 'tt_content' || $event->getRecordType() !== self::CTYPE) {
            return;
        }

        $record = $event->getRecord();
        $settings = $this->getSettings((string)($record['pi_flexform'] ?? ''));
        $record['pforum'] = [
            'settings' => $settings,
            'detailPageTitle' => $this->getPageTitle($settings['pidOfDetailPage'] ?? 0),
        ];

        $event->setRecord($record);
    }

    /**
     * @return array<string, mixed>
     */
    private function getSettings(string $flexForm): array
    {
        $settings = $flexForm === '' ? [] : $this->flexFormService->convertFlexFormContentToArray($flexForm)['settings'] ?? [];

        return is_array($settings) ? $settings : [];
    }

    private function getPageTitle(mixed $uid): string
    {
        $page = is_numeric($uid) && (int)$uid > 0 ? BackendUtility::getRecord('pages', (int)$uid, 'title') : null;

        return (string)($page['title'] ?? '');
    }
}
