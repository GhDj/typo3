<?php

return [
    'ctrl' => [
        'title' => 'LLL:EXT:thw_selfservice/Resources/Private/Language/locallang_db.xlf:tx_thwselfservice_domain_model_organizationalunit',
        'label' => 'title',
        'label_alt' => 'code',
        'label_alt_force' => true,
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'default_sortby' => 'title',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'sortby' => 'sorting',
        'iconfile' => 'EXT:core/Resources/Public/Icons/T3Icons/svgs/content/content-text.svg',
        'searchFields' => 'title,code',
    ],
    'types' => [
        '1' => [
            'showitem' => '
                --div--;LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.general,
                    title, code, parent, active,
                --div--;LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.access,
                    hidden,
            ',
        ],
    ],
    'columns' => [
        'title' => [
            'label' => 'LLL:EXT:thw_selfservice/Resources/Private/Language/locallang_db.xlf:tx_thwselfservice_domain_model_organizationalunit.title',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 255,
                'required' => true,
            ],
        ],
        'code' => [
            'label' => 'LLL:EXT:thw_selfservice/Resources/Private/Language/locallang_db.xlf:tx_thwselfservice_domain_model_organizationalunit.code',
            'config' => [
                'type' => 'input',
                'size' => 16,
                'max' => 16,
                'required' => true,
                'eval' => 'trim,upper',
            ],
        ],
        'parent' => [
            'label' => 'LLL:EXT:thw_selfservice/Resources/Private/Language/locallang_db.xlf:tx_thwselfservice_domain_model_organizationalunit.parent',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_thwselfservice_domain_model_organizationalunit',
                'items' => [
                    ['label' => '', 'value' => 0],
                ],
                'default' => 0,
            ],
        ],
        'active' => [
            'label' => 'LLL:EXT:thw_selfservice/Resources/Private/Language/locallang_db.xlf:tx_thwselfservice_domain_model_organizationalunit.active',
            'config' => [
                'type' => 'check',
                'default' => 1,
            ],
        ],
    ],
];
