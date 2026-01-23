<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Backend\Preview;

use TYPO3\CMS\Backend\Preview\StandardContentPreviewRenderer;
use TYPO3\CMS\Backend\View\BackendLayout\Grid\GridColumnItem;
use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Core\View\ViewInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Add plugin preview for EXT:pforum
 */
class PluginPreview extends StandardContentPreviewRenderer
{
    private const PREVIEW_TEMPLATE_PATH = 'EXT:pforum/Resources/Private/Templates/PluginPreview/';

    private const ALLOWED_PLUGINS = [
        'pforum_forum',
    ];

    public function __construct(
        protected readonly FlexFormService $flexFormService,
        protected readonly ViewFactoryInterface $viewFactory,
    ) {}

    public function renderPageModulePreviewContent(GridColumnItem $item): string
    {
        $ttContentRecord = $item->getRecord();
        if (!$this->isValidPlugin($ttContentRecord)) {
            return '';
        }

        $template = self::PREVIEW_TEMPLATE_PATH . 'Forum.html';

        $view = $this->viewFactory->create(
            new ViewFactoryData(
                templatePathAndFilename: $template,
            ),
        );
        $view->assignMultiple($ttContentRecord);

        $this->addPluginName($view, $ttContentRecord);

        // Add data from column pi_flexform
        $piFlexformData = $this->getPiFlexformData($ttContentRecord);
        if ($piFlexformData !== []) {
            $view->assign('pi_flexform_transformed', $piFlexformData);
        }

        return $view->render();
    }

    /**
     * @param array<string, mixed> $ttContentRecord
     */
    protected function isValidPlugin(array $ttContentRecord): bool
    {
        if (!isset($ttContentRecord['CType'])) {
            return false;
        }

        if (!in_array($ttContentRecord['CType'], self::ALLOWED_PLUGINS, true)) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $ttContentRecord
     */
    protected function addPluginName(ViewInterface $view, array $ttContentRecord): void
    {
        $langKey = sprintf(
            'plugin.%s.title',
            str_replace('pforum_', '', $ttContentRecord['CType']),
        );

        $view->assign(
            'pluginName',
            LocalizationUtility::translate('LLL:EXT:pforum/Resources/Private/Language/locallang_db.xlf:' . $langKey),
        );
    }

    /**
     * @param array<string, mixed> $ttContentRecord
     * @return array<string, mixed>
     */
    protected function getPiFlexformData(array $ttContentRecord): array
    {
        $data = [];
        if ((int)($ttContentRecord['pi_flexform'] ?? '') !== '') {
            $data = $this->flexFormService->convertFlexFormContentToArray($ttContentRecord['pi_flexform']);
        }

        return $data;
    }
}
