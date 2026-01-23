<?php

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

if (!defined('TYPO3')) {
    die('Access denied.');
}

use JWeiland\Pforum\Backend\Preview\PluginPreview;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

ExtensionUtility::registerPlugin(
    'Pforum',
    'Forum',
    'LLL:EXT:pforum/Resources/Private/Language/locallang_db.xlf:plugin.pforum.title',
    'ext-pforum-wizard-icon',
    'Pforum',
    'LLL:EXT:pforum/Resources/Private/Language/locallang_db.xlf:plugin.pforum.description',
);

ExtensionManagementUtility::addToAllTCAtypes(
    'tt_content',
    '--div--;Configuration,pi_flexform,',
    'pforum_forum',
    'after:subheader',
);

ExtensionManagementUtility::addPiFlexFormValue(
    '*',
    'FILE:EXT:pforum/Configuration/FlexForms/Forum.xml',
    'pforum_forum',
);

$GLOBALS['TCA']['tt_content']['types']['pforum_forum']['previewRenderer'] = PluginPreview::class;
