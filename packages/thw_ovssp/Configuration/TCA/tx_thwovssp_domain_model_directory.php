<?php

return [
    'ctrl' => [
        'title' => 'LLL:EXT:thw_ovssp/Resources/Private/Language/locallang_db.xlf:tx_thwovssp_domain_model_directory',
        'label' => 'name',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'default_sortby' => 'sort_key ASC, name ASC',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'sortby' => 'sorting',
        'iconfile' => 'EXT:core/Resources/Public/Icons/T3Icons/svgs/apps/pagetree-folder-default.svg',
        'searchFields' => 'name,description',
    ],
    'types' => [
        '1' => [
            'showitem' => '
                --div--;LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.general,
                    name, description, allows_read, allows_write, sort_key,
                --div--;LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.access,
                    hidden,
            ',
        ],
    ],
    'columns' => [
        'name' => [
            'label' => 'LLL:EXT:thw_ovssp/Resources/Private/Language/locallang_db.xlf:tx_thwovssp_domain_model_directory.name',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 64,
                'required' => true,
                'eval' => 'trim',
            ],
        ],
        'allows_read' => [
            'label' => 'LLL:EXT:thw_ovssp/Resources/Private/Language/locallang_db.xlf:tx_thwovssp_domain_model_directory.allows_read',
            'config' => [
                'type' => 'check',
                'default' => 0,
            ],
        ],
        'allows_write' => [
            'label' => 'LLL:EXT:thw_ovssp/Resources/Private/Language/locallang_db.xlf:tx_thwovssp_domain_model_directory.allows_write',
            'config' => [
                'type' => 'check',
                'default' => 0,
            ],
        ],
        'sort_key' => [
            'label' => 'LLL:EXT:thw_ovssp/Resources/Private/Language/locallang_db.xlf:tx_thwovssp_domain_model_directory.sort_key',
            'config' => [
                'type' => 'number',
                'size' => 5,
            ],
        ],
        'description' => [
            'label' => 'LLL:EXT:thw_ovssp/Resources/Private/Language/locallang_db.xlf:tx_thwovssp_domain_model_directory.description',
            'config' => [
                'type' => 'input',
                'size' => 50,
                'max' => 255,
            ],
        ],
    ],
];
