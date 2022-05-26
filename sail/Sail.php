<?php

namespace SailCMS;

class Sail
{
    private static string $workingDirectory = '';
    private static object|null $currentApp = null;

    public static function init(string $execPath): void
    {
        static::$workingDirectory = dirname($execPath);

        // Detect what site we are on
        $environments = [];
        include_once static::$workingDirectory . '/config/apps.env.php';

        print_r($environments);

        // detect what site we are on
        // load site config
        // load site env
        // load site containers
        // load site modules
    }
}