<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use JWeiland\Pforum\Controller\AdministrationController;

return [
    'pforumBackendModule' => [
        'parent' => 'tools',
        'position' => ['after' => 'tools_maintenance'],
        'access' => 'user',
        'workspaces' => 'live',
        'path' => '/module/web/pforum',
        'labels' => 'LLL:EXT:pforum/Resources/Private/Language/locallang_mod_administration.xlf',
        'iconIdentifier' => 'ext-pforum-wizard-icon',
        'extensionName' => 'pforum',
        'controllerActions' => [
            AdministrationController::class => [
                'index', 'listHiddenTopics', 'listHiddenPosts', 'activateTopic', 'activatePost',
            ],
        ],
    ],
];
