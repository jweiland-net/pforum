<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

return [
    'ctrl' => [
        'title' => 'LLL:EXT:pforum/Resources/Private/Language/locallang_db.xlf:tx_pforum_domain_model_post',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',

        'versioningWS' => true,
        'origUid' => 't3_origuid',
        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l10n_parent',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
            'starttime' => 'starttime',
            'endtime' => 'endtime',
        ],
        'searchFields' => 'title,description,user,',
        'iconfile' => 'EXT:pforum/Resources/Public/Icons/tx_pforum_domain_model_post.svg',
    ],
    'types' => [
        '1' => [
            'showitem' => '--palette--;;languageHidden, title, description,
            anonymous_user, frontend_user, images,
            --div--;LLL:EXT:frontend/Resources/Private/Language/locallang_tca.xlf:pages.tabs.access,
            --palette--;LLL:EXT:frontend/Resources/Private/Language/locallang_tca.xlf:pages.palettes.access;access',
        ],
    ],
    'palettes' => [
        'languageHidden' => [
            'showitem' => 'hidden, sys_language_uid, l10n_parent',
        ],
        'access' => [
            'showitem' => 'starttime;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:starttime_formlabel,endtime;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:endtime_formlabel',
        ],
    ],
    'columns' => [
        'title' => [
            'exclude' => true,
            'label' => 'LLL:EXT:pforum/Resources/Private/Language/locallang_db.xlf:tx_pforum_domain_model_post.title',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
            ],
        ],
        'description' => [
            'exclude' => true,
            'label' => 'LLL:EXT:pforum/Resources/Private/Language/locallang_db.xlf:tx_pforum_domain_model_post.description',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
            ],
        ],
        'anonymous_user' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:pforum/Resources/Private/Language/locallang_db.xlf:tx_pforum_domain_model_post.user',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_pforum_domain_model_anonymoususer',
                'minitems' => 0,
                'maxitems' => 1,
            ],
        ],
        'frontend_user' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:pforum/Resources/Private/Language/locallang_db.xlf:tx_pforum_domain_model_post.user',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'fe_users',
                'items' => [
                    ['label' => '', 'value' => ''],
                ],
                'minitems' => 0,
                'maxitems' => 1,
            ],
        ],
        'images' => [
            'exclude' => true,
            'label' => 'LLL:EXT:pforum/Resources/Private/Language/locallang_db.xlf:tx_pforum_domain_model_post.images',
            'config' => [
                'type' => 'file',
                'minitems' => 0,
                'maxitems' => 5,
            ],
        ],
        'topic' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
    ],
];
