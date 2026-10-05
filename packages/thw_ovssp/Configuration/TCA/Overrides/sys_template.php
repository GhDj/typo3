<?php

defined('TYPO3') or die();

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

ExtensionManagementUtility::addStaticFile(
    'thw_ovssp',
    'Configuration/TypoScript/',
    'THW OV-SSP'
);
