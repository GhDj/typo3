<?php

defined('TYPO3') or die();

use Init\Thw\Ovssp\Controller\PortalController;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

ExtensionUtility::configurePlugin(
    'ThwOvssp',
    'Portal',
    [
        PortalController::class => 'index,userDetail,assignRole,removeRole',
    ],
    [
        PortalController::class => 'assignRole,removeRole',
    ]
);

ExtensionUtility::configurePlugin(
    'ThwOvssp',
    'Login',
    [
        PortalController::class => 'login',
    ],
    []
);
