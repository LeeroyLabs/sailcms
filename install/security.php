<?php

$securitySettings = [
    // Hide these variables from being exposed in the $_ENV dump in the error manager
    'envBlacklist' => [
        'DATABASE_DSN',
        'DATABASE_USER',
        'DATABASE_PASSWORD',
        'DATABASE_DB',
        'DATABASE_PORT',
        'DATABASE_AUTH_DB',
        'MEILI_HOST',
        'SEARCH_ENGINE',
        'MAIL_DSN',
        'MEILI_PORT',
        'MEILI_INDEX',
        'MEILI_MASTER_KEY',
        'SETTINGS',
        'DD_DEFAULT_KEY',
        'FS3_API_KEY',
        'FS3_API_SECRET',
        'FS3_REGION',
        'CACHE_HOST',
        'CACHE_USER',
        'CACHE_PASSWORD',
        'AWS_SESSION_TOKEN',        // Secure for a serverless install
        'AWS_SECRET_ACCESS_KEY',    // Secure for a serverless install
        'AWS_ACCESS_KEY_ID'         // Secure for a serverless install
    ]
];