<?php

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

return [
    'ext-pforum-wizard-icon' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:pforum/Resources/Public/Icons/module.svg',
    ],
    'ext-pforum-table-forum' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:pforum/Resources/Public/Icons/tx_pforum_domain_model_forum.svg',
    ],
    'ext-pforum-table-topic' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:pforum/Resources/Public/Icons/tx_pforum_domain_model_topic.svg',
    ],
    'ext-pforum-table-post' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:pforum/Resources/Public/Icons/tx_pforum_domain_model_post.svg',
    ],
];
