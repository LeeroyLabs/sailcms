<?php

$config = [
    'dev' => [
        'devMode' => true,
        'allowAdmin' => true,
        'adminTrigger' => 'admin',
        'permissions' => [
            'user' => 'marcgiroux',
            'group' => 'marcgiroux'
        ],
        'errors' => [
            '404' => '404.html',
            '500' => '500.html'
        ],
        'templating' => [
            'cache' => false,
            'vueCompat' => false
        ]
    ],
    'staging' => [
        'devMode' => true,
        'allowAdmin' => true,
        'adminTrigger' => 'admin',
        'permissions' => [
            'user' => 'ubuntu',
            'group' => 'www-data'
        ],
        'errors' => [
            '404' => '404.html',
            '500' => '500.html'
        ],
        'templating' => [
            'cache' => false,
            'vueCompat' => false
        ]
    ],
    'production' => [
        'devMode' => false,
        'allowAdmin' => false,
        'adminTrigger' => 'admin',
        'permissions' => [
            'user' => 'ubuntu',
            'group' => 'www-data'
        ],
        'errors' => [
            '404' => '404.html',
            '500' => '500.html'
        ],
        'templating' => [
            'cache' => false,
            'vueCompat' => false
        ]
    ]
];