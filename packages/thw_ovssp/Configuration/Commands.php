<?php

return [
    'ovssp:setup-demo' => [
        'class' => \Init\Thw\Ovssp\Command\SetupDemoCommand::class,
    ],
    'ovssp:reset-demo' => [
        'class' => \Init\Thw\Ovssp\Command\ResetDemoCommand::class,
    ],
    'ovssp:verify-login' => [
        'class' => \Init\Thw\Ovssp\Command\VerifyLoginCommand::class,
    ],
];
