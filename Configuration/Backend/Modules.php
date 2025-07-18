<?php

declare(strict_types=1);

use JWeiland\Pforum\Controller\AdministrationController;

return [
    'pforumBackendModule' => [
        'parent' => 'tools',
        'position' => ['after' => 'tools_maintenance'],
        'access' => 'user,group',
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
