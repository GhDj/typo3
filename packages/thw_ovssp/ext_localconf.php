<?php

defined('TYPO3') or die();

use Init\Thw\Ovssp\Controller\PortalController;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

// Allow fe_user authentication from any storage PID
$GLOBALS['TYPO3_CONF_VARS']['FE']['checkFeUserPid'] = false;

ExtensionUtility::configurePlugin(
    'ThwOvssp',
    'Portal',
    [
        PortalController::class => 'index,userDetail,assignRole,removeRole',
    ],
    [
        PortalController::class => 'index,userDetail,assignRole,removeRole',
    ]
);

ExtensionUtility::configurePlugin(
    'ThwOvssp',
    'Login',
    [
        PortalController::class => 'login',
    ],
    [
        PortalController::class => 'login',
    ]
);
