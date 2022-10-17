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
        'MEILI_PORT',
        'MEILI_INDEX',
        'MEILI_MASTER_KEY',
        'SETTINGS',
        'DD_DEFAULT_KEY'
    ]
];