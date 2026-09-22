<?php

use Init\Thw\Ovssp\Controller\ImportController;

return [
    'admin_thwovssp_import' => [
        'parent' => 'admin',
        'position' => ['after' => '*'],
        'access' => 'admin',
        'iconIdentifier' => 'module-install',
        'labels' => 'LLL:EXT:thw_ovssp/Resources/Private/Language/locallang_mod.xlf',
        'path' => '/module/admin/ovssp-import',
        'routeOptions' => [
            'packageName' => 'thw/thw-ovssp',
        ],
        'routes' => [
            '_default' => [
                'target' => ImportController::class . '::indexAction',
            ],
            'upload' => [
                'target' => ImportController::class . '::uploadAction',
                'methods' => ['POST'],
            ],
            'list-orgunits' => [
                'target' => ImportController::class . '::listOrgunitsAction',
            ],
            'list-directories' => [
                'target' => ImportController::class . '::listDirectoriesAction',
            ],
            'list-users' => [
                'target' => ImportController::class . '::listUsersAction',
            ],
        ],
    ],
];
