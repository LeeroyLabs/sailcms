<?php

$config = [
    'dev' => [
        'devMode' => true,
        'allowAdmin' => true,
        'adminTrigger' => 'admin'
    ],
    'staging' => [
        'devMode' => true,
        'allowAdmin' => true,
        'adminTrigger' => 'admin'
    ],
    'production' => [
        'devMode' => false,
        'allowAdmin' => false,
        'adminTrigger' => 'admin'
    ]
];