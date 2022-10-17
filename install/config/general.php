<?php

$config = [
    'dev' => [
        'devMode' => true,
        'allowAdmin' => true,
        'adminTrigger' => 'admin',
        'graphql' => [
            'active' => true,
            'trigger' => 'graphql',
            'depthLimit' => 5
        ],
        'session' => [
            'mode' => 'standard' // or stateless (JWT)
        ],
        'templating' => [
            'cache' => false,
            'vueCompat' => false
        ],
        'tfa' => [
            'issuer' => 'SailCMS',
            'whitelist' => 'localhost,leeroy.ca',
            'length' => 6,
            'expire' => 30,
            'format' => 'svg',
            'main_color' => '',
            'hover_color' => ''
        ],
        'logging' => [
            'useRay' => true,
            'loggerName' => 'sailcms',
            'adapters' => [
                \SailCMS\Logging\Database::class
            ],
            'datadog' => [
                'api_key_identifier' => 'DD_DEFAULT_KEY'
            ],
            'minLevel' => \Monolog\Level::Debug,
            'bubble' => true
        ]
    ],
    'staging' => [
        'devMode' => true,
        'allowAdmin' => true,
        'adminTrigger' => 'admin',
        'graphql' => [
            'active' => true,
            'trigger' => 'graphql',
            'depthLimit' => 5
        ],
        'session' => [
            'mode' => 'standard' // or stateless (JWT)
        ],
        'templating' => [
            'cache' => false,
            'vueCompat' => false
        ],
        'tfa' => [
            'issuer' => 'SailCMS',
            'whitelist' => 'localhost,leeroy.ca',
            'length' => 6,
            'expire' => 30,
            'format' => 'svg',
            'main_color' => '',
            'hover_color' => ''
        ],
        'logging' => [
            'useRay' => true,
            'loggerName' => 'sailcms',
            'adapters' => [
                \SailCMS\Logging\Database::class
            ],
            'datadog' => [
                'api_key_identifier' => 'DD_DEFAULT_KEY'
            ],
            'minLevel' => \Monolog\Level::Debug,
            'bubble' => true
        ]
    ],
    'production' => [
        'devMode' => false,
        'allowAdmin' => true,
        'adminTrigger' => 'admin',
        'graphql' => [
            'active' => true,
            'trigger' => 'graphql',
            'depthLimit' => 5
        ],
        'session' => [
            'mode' => 'standard', // or stateless (JWT)
            'ttl' => 21_600 // 6h
        ],
        'templating' => [
            'cache' => false,
            'vueCompat' => false
        ],
        'tfa' => [
            'issuer' => 'SailCMS',
            'whitelist' => 'localhost,leeroy.ca',
            'length' => 6,
            'expire' => 30,
            'format' => 'svg',
            'main_color' => '',
            'hover_color' => ''
        ],
        'logging' => [
            'useRay' => true,
            'loggerName' => 'sailcms',
            'adapters' => [
                \SailCMS\Logging\Database::class
            ],
            'datadog' => [
                'api_key_identifier' => 'DD_DEFAULT_KEY'
            ],
            'minLevel' => \Monolog\Level::Debug,
            'bubble' => true
        ]
    ]
];