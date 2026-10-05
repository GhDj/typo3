<?php

return [
    'ctrl' => [
        'title' => 'LLL:EXT:thw_ovssp/Resources/Private/Language/locallang_db.xlf:tx_thwovssp_domain_model_role',
        'label' => 'name',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'default_sortby' => 'name',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'searchFields' => 'name,role_group,description',
        'iconfile' => 'EXT:core/Resources/Public/Icons/T3Icons/svgs/content/content-beside-text-img-above-center.svg',
    ],
    'types' => [
        '1' => [
            'showitem' => '
                --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general,
                    name, role_group, description,
                --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access,
                    hidden,
            ',
        ],
    ],
    'columns' => [
        'hidden' => [
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.visible',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'items' => [
                    ['label' => '', 'invertStateDisplay' => true],
                ],
            ],
        ],
        'name' => [
            'label' => 'LLL:EXT:thw_ovssp/Resources/Private/Language/locallang_db.xlf:tx_thwovssp_domain_model_role.name',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 100,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'role_group' => [
            'label' => 'LLL:EXT:thw_ovssp/Resources/Private/Language/locallang_db.xlf:tx_thwovssp_domain_model_role.role_group',
            'config' => [
                'type' => 'input',
                'size' => 20,
                'max' => 50,
                'eval' => 'trim',
            ],
        ],
        'description' => [
            'label' => 'LLL:EXT:thw_ovssp/Resources/Private/Language/locallang_db.xlf:tx_thwovssp_domain_model_role.description',
            'config' => [
                'type' => 'text',
                'rows' => 5,
                'cols' => 40,
            ],
        ],
    ],
];
