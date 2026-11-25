<?php

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

$EM_CONF[$_EXTKEY] = [
    'title' => 'Forum',
    'description' => 'Forum',
    'category' => 'plugin',
    'author' => 'Stefan Froemken, Hoja Mustaffa Abdul Latheef',
    'author_email' => 'projects@jweiland.net',
    'author_company' => 'jweiland.net',
    'state' => 'stable',
    'version' => '9.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '14.3.0-14.3.99',
            'php' => '8.2.0-8.5.99',
        ],
        'conflicts' => [],
        'suggests' => [
            'checkfaluploads' => '7.0.0-7.99.99',
            'rte_ckeditor' => '14.3.0-14.3.99',
        ],
    ],
];
