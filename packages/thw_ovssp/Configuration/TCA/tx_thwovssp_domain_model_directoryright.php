<?php

return [
    'ctrl' => [
        'title' => 'LLL:EXT:thw_ovssp/Resources/Private/Language/locallang_db.xlf:tx_thwovssp_domain_model_directoryright',
        'label' => 'directory',
        'label_alt' => 'access',
        'label_alt_force' => true,
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'default_sortby' => 'role,directory',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'iconfile' => 'EXT:core/Resources/Public/Icons/T3Icons/svgs/content/content-beside-text-img-above-center.svg',
    ],
    'types' => [
        '1' => [
            'showitem' => '
                --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general,
                    role, directory, access,
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
        'role' => [
            'label' => 'LLL:EXT:thw_ovssp/Resources/Private/Language/locallang_db.xlf:tx_thwovssp_domain_model_directoryright.role',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_thwovssp_domain_model_role',
                'required' => true,
            ],
        ],
        'directory' => [
            'label' => 'LLL:EXT:thw_ovssp/Resources/Private/Language/locallang_db.xlf:tx_thwovssp_domain_model_directoryright.directory',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_thwovssp_domain_model_directory',
                'required' => true,
            ],
        ],
        'access' => [
            'label' => 'LLL:EXT:thw_ovssp/Resources/Private/Language/locallang_db.xlf:tx_thwovssp_domain_model_directoryright.access',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => 'LLL:EXT:thw_ovssp/Resources/Private/Language/locallang_db.xlf:tx_thwovssp_domain_model_directoryright.access.read', 'value' => 'read'],
                    ['label' => 'LLL:EXT:thw_ovssp/Resources/Private/Language/locallang_db.xlf:tx_thwovssp_domain_model_directoryright.access.write', 'value' => 'write'],
                    ['label' => 'LLL:EXT:thw_ovssp/Resources/Private/Language/locallang_db.xlf:tx_thwovssp_domain_model_directoryright.access.deny', 'value' => 'deny'],
                ],
                'default' => 'read',
                'required' => true,
            ],
        ],
    ],
];
