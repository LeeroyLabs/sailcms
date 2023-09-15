<?php

use SailCMS\Assets\Transformer;

return [
    'devMode' => true,
    'allowAdmin' => true,
    'adminTrigger' => 'admin',
    'timezone' => 'America/New_York',
    'useBasicAuthentication' => false,
    'database' => [
        'activerecord_save_whole_object' => false
    ],
    'monitoring' => [
        'problematic_sample_count_notify' => 5,
        'warning_email_address' => 'you@email.com'
    ],
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
        'usePreviewer' => true,
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
        'introspection' => true,
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
        'vueCompat' => false,
        'renderer' => 'twig'
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
        'shared' => true,
        'adapter' => 'local',
        'optimizeOnUpload' => true,
        'transformOutputFormat' => 'webp',
        'transformQuality' => 92, // 92%
        'maxUploadSize' => 5, // in MB
        'extensionBlackList' => ['exe', 'php', 'sh', 'sql'],
        'onUploadTransforms' => [
            'thumbnail' => ['width' => 150, 'height' => 150, 'crop' => Transformer::CROP_CC]
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
        'requireValidation' => false,
        'baseRole' => 'general-user'
    ]
];