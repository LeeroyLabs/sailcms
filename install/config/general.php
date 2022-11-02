<?php

use SailCMS\Assets\Transformer;

$config = [
    'dev' => [
        'devMode' => true,
        'allowAdmin' => true,
        'adminTrigger' => 'admin',
        'passwords' => [
            'minLength' => 8,
            'maxLength' => 64,
            'enforceAlphanum' => true,
            'enforceUpperLower' => true
        ],
        'CSRF' => [
            'use' => true,
            'leeway' => 5,
            'expiration' => 120
        ],
        'graphql' => [
            'active' => true,
            'trigger' => 'graphql',
            'depthLimit' => 5
        ],
        'cors' => [
            'use' => true,
            'origins' => ['*'],
            'allowCredentials' => true,
            'maxAge' => 86400,
            'methods' => ['POST', 'GET', 'DELETE', 'PUT', 'OPTIONS'],
            'headers' => ['Accept', 'Upgrade-Insecure-Requests', 'Content-Type', 'x-requested-with']
        ],
        'session' => [
            'mode' => \SailCMS\Session\Stateless::class,
            'httpOnly' => true,
            'samesite' => 'strict',
            'ttl' => 21_600, // 6h
            'jwt' => [
                'issuer' => 'SailCMS',
                'domain' => 'localhost'
            ]
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
        ],
        'assets' => [
            'adapter' => 'local',
            'optimizeOnUpload' => true,
            'transformOutputFormat' => 'webp',
            'transformQuality' => 92, // 92%
            'storeTransforms' => true,
            'maxUploadSize' => 5, // in MB
            'onUploadTransforms' => [
                'thumbnail' => ['width' => 100, 'height' => 100, 'crop' => Transformer::CROP_CC]
            ]
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
        ],
        'assets' => [
            'adapter' => 'local',
            'optimizeOnUpload' => true,
            'transformOutputFormat' => 'webp',
            'transformQuality' => 92, // 92%
            'storeTransforms' => true,
            'maxUploadSize' => 5, // in MB
            'onUploadTransforms' => [
                'thumbnail' => ['size' => '100x100', 'crop' => Transformer::CROP_CC]
            ]
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
        ],
        'assets' => [
            'adapter' => 'local',
            'optimizeOnUpload' => true,
            'transformOutputFormat' => 'webp',
            'transformQuality' => 92, // 92%
            'storeTransforms' => true,
            'maxUploadSize' => 5, // in MB
            'onUploadTransforms' => [
                'thumbnail' => ['size' => '100x100', 'crop' => Transformer::CROP_CC]
            ]
        ]
    ]
];