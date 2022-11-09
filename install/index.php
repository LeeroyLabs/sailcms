<?php

// Local development / regular server + serverless support
if (file_exists(dirname(__DIR__) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
} else {
    require_once '/tmp/vendor/autoload.php';
    SailCMS\Sail::$isServerless = true;
}

SailCMS\Sail::init(execPath: __DIR__);