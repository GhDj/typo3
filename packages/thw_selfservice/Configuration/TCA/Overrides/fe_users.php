<?php

defined('TYPO3') or die();

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

$thwColumns = [
    'thw_uid' => [
        'label' => 'LLL:EXT:thw_selfservice/Resources/Private/Language/locallang_db.xlf:fe_users.thw_uid',
        'config' => [
            'type' => 'input',
            'size' => 30,
            'max' => 32,
            'readOnly' => true,
        ],
    ],
    'thw_ou' => [
        'label' => 'LLL:EXT:thw_selfservice/Resources/Private/Language/locallang_db.xlf:fe_users.thw_ou',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'foreign_table' => 'tx_thwselfservice_domain_model_organizationalunit',
            'items' => [
                ['label' => '', 'value' => 0],
            ],
            'default' => 0,
            'readOnly' => true,
        ],
    ],
    'thw_birthdate' => [
        'label' => 'LLL:EXT:thw_selfservice/Resources/Private/Language/locallang_db.xlf:fe_users.thw_birthdate',
        'config' => [
            'type' => 'datetime',
            'format' => 'date',
            'readOnly' => true,
        ],
    ],
    'thw_active' => [
        'label' => 'LLL:EXT:thw_selfservice/Resources/Private/Language/locallang_db.xlf:fe_users.thw_active',
        'config' => [
            'type' => 'check',
            'readOnly' => true,
        ],
    ],
    'thw_portal_access' => [
        'label' => 'LLL:EXT:thw_selfservice/Resources/Private/Language/locallang_db.xlf:fe_users.thw_portal_access',
        'config' => [
            'type' => 'check',
            'default' => 0,
        ],
    ],
    'thw_sso_subject' => [
        'label' => 'LLL:EXT:thw_selfservice/Resources/Private/Language/locallang_db.xlf:fe_users.thw_sso_subject',
        // TODO Phase 2: AD claim → CSV identifier matching will plug in here.
        // The mapping between AD FS OIDC subject claim and the CSV-imported thw_uid
        // is still an open question on the project.
        'config' => [
            'type' => 'input',
            'size' => 30,
            'max' => 255,
        ],
    ],
    'thw_last_import' => [
        'label' => 'LLL:EXT:thw_selfservice/Resources/Private/Language/locallang_db.xlf:fe_users.thw_last_import',
        'config' => [
            'type' => 'datetime',
            'readOnly' => true,
        ],
    ],
];

ExtensionManagementUtility::addTCAcolumns('fe_users', $thwColumns);

ExtensionManagementUtility::addToAllTCAtypes(
    'fe_users',
    '--div--;LLL:EXT:thw_selfservice/Resources/Private/Language/locallang_db.xlf:fe_users.tab.thw, thw_uid, thw_ou, thw_birthdate, thw_active, thw_portal_access, thw_sso_subject, thw_last_import'
);
