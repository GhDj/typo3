<?php

return [
    'ctrl' => [
        'title' => 'LLL:EXT:thw_sso/Resources/Private/Language/locallang_db.xlf:tx_thwsso_domain_model_orgunit',
        'label' => 'name',
        'label_alt' => 'oe_code',
        'label_alt_force' => true,
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'default_sortby' => 'name',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'sortby' => 'sorting',
        'iconfile' => 'EXT:core/Resources/Public/Icons/T3Icons/svgs/content/content-text.svg',
        'searchFields' => 'name,oe_code,thw_oe_uid,mail_address',
    ],
    'types' => [
        '1' => [
            'showitem' => '
                --div--;LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.general,
                    thw_oe_uid, oe_code, name, mail_address,
                    regionalbereich_code, landesverband_code, active,
                --div--;LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.access,
                    hidden,
            ',
        ],
    ],
    'columns' => [
        'thw_oe_uid' => [
            'label' => 'LLL:EXT:thw_sso/Resources/Private/Language/locallang_db.xlf:tx_thwsso_domain_model_orgunit.thw_oe_uid',
            'config' => [
                'type' => 'number',
                'size' => 10,
                'readOnly' => true,
            ],
        ],
        'oe_code' => [
            'label' => 'LLL:EXT:thw_sso/Resources/Private/Language/locallang_db.xlf:tx_thwsso_domain_model_orgunit.oe_code',
            'config' => [
                'type' => 'input',
                'size' => 4,
                'max' => 4,
                'readOnly' => true,
                'eval' => 'trim',
            ],
        ],
        'name' => [
            'label' => 'LLL:EXT:thw_sso/Resources/Private/Language/locallang_db.xlf:tx_thwsso_domain_model_orgunit.name',
            'config' => [
                'type' => 'input',
                'size' => 40,
                'max' => 255,
                'required' => true,
            ],
        ],
        'mail_address' => [
            'label' => 'LLL:EXT:thw_sso/Resources/Private/Language/locallang_db.xlf:tx_thwsso_domain_model_orgunit.mail_address',
            'config' => [
                'type' => 'email',
                'size' => 40,
            ],
        ],
        'regionalbereich_code' => [
            'label' => 'LLL:EXT:thw_sso/Resources/Private/Language/locallang_db.xlf:tx_thwsso_domain_model_orgunit.regionalbereich_code',
            'config' => [
                'type' => 'input',
                'size' => 4,
                'max' => 4,
                'readOnly' => true,
                'eval' => 'trim',
            ],
        ],
        'landesverband_code' => [
            'label' => 'LLL:EXT:thw_sso/Resources/Private/Language/locallang_db.xlf:tx_thwsso_domain_model_orgunit.landesverband_code',
            'config' => [
                'type' => 'input',
                'size' => 4,
                'max' => 4,
                'readOnly' => true,
                'eval' => 'trim',
            ],
        ],
        'active' => [
            'label' => 'LLL:EXT:thw_sso/Resources/Private/Language/locallang_db.xlf:tx_thwsso_domain_model_orgunit.active',
            'config' => [
                'type' => 'check',
                'default' => 1,
            ],
        ],
    ],
];
