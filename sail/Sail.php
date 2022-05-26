<?php

namespace SailCMS;

use SailCMS\Core\Collection;

class Sail
{
    // Directories
    private static string $workingDirectory = '';
    private static string $configDirectory = '';


    private static object|null $currentApp = null;
    private static string $currentAppKey = '';

    /**
     *
     * Initialize the CMS
     *
     * @param string $execPath
     * @return void
     *
     */
    public static function init(string $execPath): void
    {
        static::$workingDirectory = dirname($execPath);

        // Detect what site we are on
        $environments = [];
        include_once static::$workingDirectory . '/config/apps.env.php';

        foreach ($environments as $name => $env) {
            $host = $_SERVER['HTTP_HOST'];

            if (in_array($host, $env['domains'], true)) {
                static::$currentApp = $env;
                static::$currentAppKey = $name;
            }
        }

        if (static::$currentAppKey === '') {
            // TODO: Fail now!
        }

        // Load configurations
        static::$configDirectory = static::$workingDirectory . '/config/' . static::$currentAppKey;

        $config = [];
        include_once static::$configDirectory . '/general.php';
        $_ENV['settings'] = new Collection($config);

        print_r($environments);

        // load site config
        // load site env
        // load site containers
        // load site modules
    }
}