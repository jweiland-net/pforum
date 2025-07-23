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
        TopicController::class => 'show, new, create, edit, update, delete, activate',
        PostController::class => 'new, create, edit, update, delete, activate',
    ],
    // non-cacheable actions
    [
        ForumController::class => 'list, show',
        TopicController::class => 'create, update, delete, activate',
        PostController::class => 'create, update, delete, activate',
    ],
);

$GLOBALS['TYPO3_CONF_VARS']['MAIL']['templateRootPaths'][1666352112] = 'EXT:pforum/Resources/Private/Templates/Mail';
