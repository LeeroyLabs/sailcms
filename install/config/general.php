<?php

use SailCMS\Assets\Transformer;

return [
    'dev' => [
        'devMode' => true,
        'allowAdmin' => true,
        'adminTrigger' => 'admin',
        'timezone' => 'America/New_York',
        'useBasicAuthentication' => false,
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
            'fromName' => [
                'fr' => 'SailCMS',
                'en' => 'SailCMS'
            ],
            'sendNewAccount' => true,
            'globalContext' => [
                // You can add your own static context variables
                'locales' => [
                    'fr' => [
                        'thanks' => 'Merci',
                        'defaultWho' => "Quelqu'un que vous connaissez"
                    ],
                    'en' => [
                        'thanks' => 'Thanks',
                        "defaultWho" => 'Someone you know'
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
            'depthLimit' => 5,
            'hideCMS' => false
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
            'database' => false,
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
                'urlPrefix' => [
                    'en' => '',
                    'fr' => ''
                ],
                'entryLayoutId' => null
            ],
            'cacheTtl' => \SailCMS\Cache::TTL_WEEK,
            'seo' => [
                'defaultDescription' => 'SailCMS site',
            ]
        ],
        'users' => [
            'requireValidation' => true,
            'baseRole' => 'general-user'
        ]
    ],
    'staging' => [
        'devMode' => true,
        'allowAdmin' => true,
        'adminTrigger' => 'admin',
        'timezone' => 'America/New_York',
        'useBasicAuthentication' => false,
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
                        'thanks' => 'Merci',
                        'defaultWho' => "Quelqu'un que vous connaissez"
                    ],
                    'en' => [
                        'thanks' => 'Thanks',
                        "defaultWho" => 'Someone you know'
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
            'depthLimit' => 5,
            'hideCMS' => false
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
            'database' => false,
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
                'urlPrefix' => [
                    'en' => '',
                    'fr' => ''
                ]
            ],
            'cacheTtl' => \SailCMS\Cache::TTL_WEEK
        ],
        'users' => [
            'requireValidation' => true,
            'baseRole' => 'general-user'
        ]
    ],
    'production' => [
        'devMode' => false,
        'allowAdmin' => true,
        'adminTrigger' => 'admin',
        'timezone' => 'America/New_York',
        'useBasicAuthentication' => false,
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
                        'thanks' => 'Merci',
                        'defaultWho' => "Quelqu'un que vous connaissez"
                    ],
                    'en' => [
                        'thanks' => 'Thanks',
                        "defaultWho" => 'Someone you know'
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
            'depthLimit' => 5,
            'hideCMS' => false
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
            'database' => false,
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
                'urlPrefix' => [
                    'en' => '',
                    'fr' => ''
                ]
            ],
            'cache_ttl' => \SailCMS\Cache::TTL_WEEK
        ],
        'users' => [
            'requireValidation' => true,
            'baseRole' => 'general-user'
        ]
    ]
];