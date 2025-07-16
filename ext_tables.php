<?php
if (!defined('TYPO3')) {
    die('Access denied.');
}

use JWeiland\Pforum\Controller\AdministrationController;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

ExtensionUtility::registerModule(
    'pforum',
    'web',
    'administration',
    '',
    [
        AdministrationController::class => 'index, listHiddenTopics, listHiddenPosts, activateTopic, activatePost',
    ],
    [
        'access' => 'user,group',
        'icon' => 'EXT:pforum/Resources/Public/Icons/module.svg',
        'labels' => 'LLL:EXT:pforum/Resources/Private/Language/locallang_mod_administration.xlf',
    ]
);
