<?php

namespace SailCMS;

use Dotenv\Dotenv;
use SailCMS\Collection;
use SailCMS\Errors\FileException;
use SailCMS\Errors\SiteException;
use SailCMS\Routing\Router;
use SailCMS\Types\ContainerInformation;
use Whoops\Run;
use Whoops\Handler\PrettyPageHandler;

class Sail
{
    // Directories
    private static string $workingDirectory = '';
    private static string $configDirectory = '';

    // Current running app
    private static string $currentApp = '';

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
        // Register the error handler
        $whoops = new Run();
        $whoops->pushHandler(new PrettyPageHandler());
        $whoops->register();

        static::$workingDirectory = dirname($execPath);

        // Detect what site we are on
        $environments = [];
        include_once static::$workingDirectory . '/config/apps.env.php';

        foreach ($environments as $name => $env) {
            $host = explode(':', $_SERVER['HTTP_HOST'])[0];

            if (in_array($host, $env['domains'], true)) {
                static::$currentApp = $name;
            }
        }

        if (static::$currentApp === '') {
            // TODO: Fail now!
            throw new SiteException('No site found for the current host, please make sure its not a mistake.', 0500);
        }

        // Load configurations
        static::$configDirectory = static::$workingDirectory . '/config/' . static::$currentApp;

        // Load .env file
        $dotenv = Dotenv::createImmutable(static::$workingDirectory, $environments[static::$currentApp]['file']);
        $dotenv->load();

        $config = [];
        include_once static::$configDirectory . '/general.php';
        $settings = new Collection($config);

        $_ENV['settings'] = $settings->get($_ENV['ENVIRONMENT'] ?? 'dev');
        
        // Initialize the router
        Router::init();

        // Load all site's containers
        static::loadContainers();

        // Ensure peak performance from the database
        static::ensurePerformance();

        // load site modules

        Router::dispatch();
    }

    // -------------------------------------------------- Private --------------------------------------------------- //

    /**
     *
     * @throws FileException
     * @throws \JsonException
     * @throws Core\Errors\DatabaseException
     *
     */
    private static function loadContainers(): void
    {
        // Read the application level composer.json file
        $composerFile = static::$workingDirectory . '/composer.json';
        $composer = json_decode(file_get_contents($composerFile), false, 512, JSON_THROW_ON_ERROR);

        if ($composer->sailcms && $composer->sailcms->containers) {
            foreach ($composer->sailcms->containers as $containerName) {
                $className = $containerName . '\\Container';

                if (class_exists($className)) {
                    $instance = new $className();
                    $info = $instance->info();

                    // Register the container, if required
                    Register::instance()->registerContainer($info, $className);

                    if ($info->sites->contains(static::$currentApp)) {
                        // Install the routes
                        $instance->routes();
                    }
                } else {
                    throw new FileException("Container {$className} does not exist or is not named properly. Please verify and try again.", 0404);
                }
            }
        }
    }

    private static function ensurePerformance(): void
    {
        $models = new Collection(glob(__DIR__ . '/Models/*.php'));

        $models->each(function ($key, $value)
        {
            $name = substr(basename($value), 0, -4);
            $class = 'SailCMS\\Models\\' . $name;
            $class::ensureIndexes();
        });
    }
}