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
        'extensionName' => 'ThwOvssp',
        'routeOptions' => [
            'packageName' => 'thw/thw-ovssp',
        ],
        'controllerActions' => [
            ImportController::class => [
                'index',
                'upload',
            ],
        ],
    ],
];
