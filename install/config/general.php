<?php

use SailCMS\Assets\Transformer;

$config = [
    'dev' => [
        'devMode' => true,
        'allowAdmin' => true,
        'adminTrigger' => 'admin',
        'cache' => [
            'use' => (bool)env('cache_use', 'false'),
            'host' => env('cache_host', 'localhost'),
            'user' => env('cache_user', ''),
            'password' => env('cache_password', ''),
            'port' => 6379,
            'database' => 10,
            'ssl' => [
                'verify' => true,
                'cafile' => '/path/to/file'
            ]
        ],
        'emails' => [
            'from' => 'no-reply@leeroy.ca',
            'sendNewAccount' => false,
            'globalContext' => [
                // You can add your own static context variables
                'locales' => [
                    'fr' => [
                        'follow' => 'Suivez-nous',
                        'privacy' => 'Politique de confidentialité',
                        'contact' => 'Contactez nous',
                        'faq' => 'FAQ',
                        'team' => "L'équipe Cubeler",
                        'thanks' => 'Merci'
                    ],
                    'en' => [
                        'follow' => 'Follow Us',
                        'privacy' => 'Privacy Policy',
                        'contact' => 'Contact Us',
                        'faq' => 'FAQ',
                        'team' => 'The Cubeler Team',
                        'thanks' => 'Thank'
                    ]
                ]
            ]
        ],
        'passwords' => [
            'minLength' => 8,
            'maxLength' => 64,
            'enforceAlphanum' => true,
            'enforceUpperLower' => true,
            'enforceSpecialChars' => true
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
            'maxUploadSize' => 5, // in MB
            'onUploadTransforms' => [
                'thumbnail' => ['width' => 100, 'height' => 100, 'crop' => Transformer::CROP_CC]
            ]
        ],
        'entry' => [
            'defaultType' => [
                'title' => 'Page',
                'handle' => 'page',
                'url_prefix' => ''
            ]
        ]
    ],
    'staging' => [
        'devMode' => true,
        'allowAdmin' => true,
        'adminTrigger' => 'admin',
        'cache' => [
            'use' => (bool)env('cache_use', 'false'),
            'host' => env('cache_host', 'localhost'),
            'user' => env('cache_user', ''),
            'password' => env('cache_password', ''),
            'port' => 6379,
            'database' => 10,
            'ssl' => [
                'verify' => true,
                'cafile' => '/path/to/file'
            ]
        ],
        'emails' => [
            'from' => 'no-reply@sailcms.io',
            'sendNewAccount' => false,
            'globalContext' => [
                // You can add your own static context variables
                'locales' => [
                    'fr' => [
                        // Strings that are not dynamic are translated here
                    ],
                    'en' => [
                        // Strings that are not dynamic are translated here
                    ]
                ]
            ]
        ],
        'passwords' => [
            'minLength' => 8,
            'maxLength' => 64,
            'enforceAlphanum' => true,
            'enforceUpperLower' => true,
            'enforceSpecialChars' => true
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
            'maxUploadSize' => 5, // in MB
            'onUploadTransforms' => [
                'thumbnail' => ['size' => '100x100', 'crop' => Transformer::CROP_CC]
            ]
        ],
        'entry' => [
            'defaultType' => [
                'title' => 'Page',
                'handle' => 'page',
                'url_prefix' => ''
            ]
        ]
    ],
    'production' => [
        'devMode' => false,
        'allowAdmin' => true,
        'adminTrigger' => 'admin',
        'cache' => [
            'use' => (bool)env('cache_use', 'false'),
            'host' => env('cache_host', 'localhost'),
            'user' => env('cache_user', ''),
            'password' => env('cache_password', ''),
            'port' => 6379,
            'database' => 10,
            'ssl' => [
                'verify' => true,
                'cafile' => '/path/to/file'
            ]
        ],
        'emails' => [
            'from' => 'no-reply@sailcms.io',
            'sendNewAccount' => false,
            'globalContext' => [
                // You can add your own static context variables
                'locales' => [
                    'fr' => [
                        // Strings that are not dynamic are translated here
                    ],
                    'en' => [
                        // Strings that are not dynamic are translated here
                    ]
                ]
            ]
        ],
        'passwords' => [
            'minLength' => 8,
            'maxLength' => 64,
            'enforceAlphanum' => true,
            'enforceUpperLower' => true,
            'enforceSpecialChars' => true
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
            'maxUploadSize' => 5, // in MB
            'onUploadTransforms' => [
                'thumbnail' => ['size' => '100x100', 'crop' => Transformer::CROP_CC]
            ]
        ],
        'entry' => [
            'defaultType' => [
                'title' => 'Page',
                'handle' => 'page',
                'url_prefix' => ''
            ]
        ]
    ]
];