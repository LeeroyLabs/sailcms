<?php

use SailCMS\Assets\Transformer;

return [
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
        'from' => 'no-reply@leeroy.ca',
        'fromName' => [
            'fr' => 'SailCMS',
            'en' => 'SailCMS'
        ],
        'usePreviewer' => false,
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
        ],
        'overrides' => [
            'allow' => true,
            'acceptedDomains' => ['localhost', 'localhost:5173', 'localhost:3000']
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
        'expiration' => 120,
        'fieldName' => '_csrf_' // You should make this a random value to be unique
    ],
    'graphql' => [
        'active' => true,
        'trigger' => 'graphql',
        'depthLimit' => 5,
        'introspection' => false,
        'hideCMS' => false
    ],
    'cors' => [
        'use' => true,      // If using serverless, this should be set to false and CORS should be set in serverless platform
        'origin' => '*',
        'allowCredentials' => true,
        'maxAge' => 86400,
        'methods' => ['POST', 'GET', 'DELETE', 'PUT', 'OPTIONS'],
        'headers' => [
            'Content-Type',
            'x-access-token',
            'x-domain-override'
        ]
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
        'extensionBlackList' => ['exe', 'php', 'sh', 'sql'],
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
            'entryLayoutId' => null,
            'useCategories' => false
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
];