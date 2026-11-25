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
        'title' => 'LLL:EXT:pforum/Resources/Private/Language/locallang_db.xlf:tx_pforum_domain_model_forum',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'sortby' => 'sorting',
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
        'iconfile' => 'EXT:pforum/Resources/Public/Icons/tx_pforum_domain_model_forum.svg',
    ],
    'types' => [
        '1' => [
            'showitem' => '--palette--;;languageHidden, title, teaser, topics,
            --div--;core.form.tabs:access,
            --palette--;core.form.palettes:access;access',
        ],
    ],
    'palettes' => [
        'languageHidden' => ['showitem' => 'hidden, sys_language_uid, l10n_parent'],
        'access' => [
            'showitem' => 'starttime;core.db.general:starttime,endtime;core.db.general:endtime',
        ],
    ],
    'columns' => [
        'title' => [
            'exclude' => true,
            'label' => 'LLL:EXT:pforum/Resources/Private/Language/locallang_db.xlf:tx_pforum_domain_model_forum.title',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'teaser' => [
            'exclude' => true,
            'label' => 'LLL:EXT:pforum/Resources/Private/Language/locallang_db.xlf:tx_pforum_domain_model_forum.teaser',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
            ],
        ],
        'topics' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:pforum/Resources/Private/Language/locallang_db.xlf:tx_pforum_domain_model_forum.topics',
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_pforum_domain_model_topic',
                'foreign_field' => 'forum',
                'maxitems' => 9999,
                'appearance' => [
                    'collapseAll' => 0,
                    'levelLinksPosition' => 'top',
                    'showSynchronizationLink' => 1,
                    'showPossibleLocalizationRecords' => 1,
                    'showAllLocalizationLink' => 1,
                ],
            ],
        ],
    ],
];
