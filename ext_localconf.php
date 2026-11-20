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

use JWeiland\Pforum\Controller\ForumController;
use JWeiland\Pforum\Controller\PostController;
use JWeiland\Pforum\Controller\TopicController;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

ExtensionUtility::configurePlugin(
    'pforum',
    'Forum',
    [
        ForumController::class => 'list, show',
        TopicController::class => 'show, new, edit, delete, confirmActivation, activate',
        PostController::class => 'new, edit, delete, confirmActivation, activate',
    ],
    // non-cacheable actions
    [
        ForumController::class => 'list, show',
        TopicController::class => 'show, new, edit, delete, confirmActivation, activate',
        PostController::class => 'new, edit, delete, confirmActivation, activate',
    ],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT,
);
