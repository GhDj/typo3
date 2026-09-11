<?php

use Init\Thw\Ovssp\Controller\ImportController;

return [
    'tools_thwovssp_import' => [
        'parent' => 'tools',
        'position' => ['after' => '*'],
        'access' => 'admin',
        'labels' => 'LLL:EXT:thw_ovssp/Resources/Private/Language/locallang_mod.xlf',
        'iconIdentifier' => 'module-install',
        'routes' => [
            '_default' => [
                'target' => ImportController::class . '::indexAction',
            ],
            'upload' => [
                'target' => ImportController::class . '::uploadAction',
                'methods' => ['POST'],
            ],
        ],
    ],
];
