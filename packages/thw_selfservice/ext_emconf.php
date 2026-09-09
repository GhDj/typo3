<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'THW Self-Service',
    'description' => 'Self-service permission registry for THW Ortsverbände',
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
