<?php

defined('TYPO3') or die();

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

$thwColumns = [
    'thw_uid' => [
        'label' => 'LLL:EXT:thw_ovssp/Resources/Private/Language/locallang_db.xlf:fe_users.thw_uid',
        'config' => [
            'type' => 'number',
            'size' => 10,
            'readOnly' => true,
        ],
    ],
    'thw_orgunit' => [
        'label' => 'LLL:EXT:thw_ovssp/Resources/Private/Language/locallang_db.xlf:fe_users.thw_orgunit',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'foreign_table' => 'tx_thwovssp_domain_model_orgunit',
            'items' => [
                ['label' => '', 'value' => 0],
            ],
            'default' => 0,
            'readOnly' => true,
        ],
    ],
    'thw_realm' => [
        'label' => 'LLL:EXT:thw_ovssp/Resources/Private/Language/locallang_db.xlf:fe_users.thw_realm',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'items' => [
                ['label' => '', 'value' => ''],
                ['label' => 'HA (Hauptamt)', 'value' => 'HA'],
                ['label' => 'EA (Ehrenamt)', 'value' => 'EA'],
            ],
            'default' => '',
            'readOnly' => true,
        ],
    ],
    'thw_birthdate' => [
        'label' => 'LLL:EXT:thw_ovssp/Resources/Private/Language/locallang_db.xlf:fe_users.thw_birthdate',
        'config' => [
            'type' => 'datetime',
            'dbType' => 'date',
            'format' => 'date',
        ],
    ],
    'thw_portal_access' => [
        'label' => 'LLL:EXT:thw_ovssp/Resources/Private/Language/locallang_db.xlf:fe_users.thw_portal_access',
        'config' => [
            'type' => 'check',
            'default' => 0,
        ],
    ],
    'thw_last_import' => [
        'label' => 'LLL:EXT:thw_ovssp/Resources/Private/Language/locallang_db.xlf:fe_users.thw_last_import',
        'config' => [
            'type' => 'datetime',
            'dbType' => 'datetime',
            'readOnly' => true,
        ],
    ],
    'thw_import_missing_since' => [
        'label' => 'LLL:EXT:thw_ovssp/Resources/Private/Language/locallang_db.xlf:fe_users.thw_import_missing_since',
        'config' => [
            'type' => 'datetime',
            'dbType' => 'datetime',
            'readOnly' => true,
        ],
    ],
];

ExtensionManagementUtility::addTCAcolumns('fe_users', $thwColumns);

ExtensionManagementUtility::addToAllTCAtypes(
    'fe_users',
    '--div--;LLL:EXT:thw_ovssp/Resources/Private/Language/locallang_db.xlf:fe_users.tab.thw, thw_uid, thw_orgunit, thw_realm, thw_birthdate, thw_portal_access, thw_last_import, thw_import_missing_since'
);
