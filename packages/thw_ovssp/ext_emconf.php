<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'THW OV-SSP',
    'description' => 'Self-Service-Portal for THW Ortsverbände — permission registry with CSV import from Active Directory',
    'category' => 'fe',
    'version' => '0.1.0',
    'state' => 'alpha',
    'author' => 'THW Development',
    'constraints' => [
        'depends' => [
            'typo3' => '14.0.0-14.99.99',
            'extbase' => '14.0.0-14.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
