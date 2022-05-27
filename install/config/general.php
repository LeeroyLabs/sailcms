<?php

$config = [
    'dev' => [
        'devMode' => true,
        'allowAdmin' => true,
        'adminTrigger' => 'admin',
        'permissions' => [
            'user' => 'marcgiroux',
            'group' => 'marcgiroux'
        ]
    ],
    'staging' => [
        'devMode' => true,
        'allowAdmin' => true,
        'adminTrigger' => 'admin',
        'permissions' => [
            'user' => 'ubuntu',
            'group' => 'www-data'
        ]
    ],
    'production' => [
        'devMode' => false,
        'allowAdmin' => false,
        'adminTrigger' => 'admin',
        'permissions' => [
            'user' => 'ubuntu',
            'group' => 'www-data'
        ]
    ]
];