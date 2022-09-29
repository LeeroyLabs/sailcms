<?php

namespace SailCMS;

use Dotenv\Dotenv;
use JsonException;
use League\Flysystem\FilesystemException;
use SailCMS\Errors\FileException;
use SailCMS\Errors\SiteException;
use SailCMS\Middleware\Data;
use SailCMS\Middleware\Http;
use SailCMS\Routing\Router;
use SailCMS\Types\MiddlewareType;
use Whoops\Handler\JsonResponseHandler;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Handler\PlainTextHandler;
use Whoops\Run;

class Sail
{
    // Directories
    private static string $workingDirectory = '';
    private static string $configDirectory = '';
    private static string $templateDirectory = '';
    private static string $cacheDirectory = '';
    private static string $fsDirectory = '';

    // Current running app
    private static string $currentApp = '';

    // Error Handler
    private static Run $errorHandler;

    /**
     *
     * Initialize the CMS
     *
     * @param string $execPath
     * @return void
     *
     * @throws Errors\DatabaseException
     * @throws Errors\RouteReturnException
     * @throws FileException
     * @throws JsonException
     * @throws SiteException
     * @throws FilesystemException
     *
     */
    public static function init(string $execPath): void
    {
        static::$workingDirectory = dirname($execPath);

        $securitySettings = [];
        include_once static::$workingDirectory . '/config/security.php';

        // Register the error handler
        static::$errorHandler = new Run();
        $ct = getallheaders()['Content-Type'] ?? '';
        $isWeb = false;

        if (!empty($ct) && stripos($ct, 'application/json') !== false) {
            $ph = new JsonResponseHandler();
        } else {
            $ph = new PrettyPageHandler();
            $isWeb = true;
        }

        if ($isWeb) {
            foreach ($securitySettings['envBlacklist'] as $key => $value) {
                $ph->blacklist('_ENV', $value);
            }
        }

        static::$errorHandler->pushHandler($ph);
        static::$errorHandler->register();

        static::bootBasics($securitySettings);

        if ($_SERVER['REQUEST_URI'] === '/' . $_ENV['SETTINGS']->get('graphql.trigger') && $_ENV['SETTINGS']->get('graphql.active')) {
            Middleware::execute(MiddlewareType::HTTP, new Data(Http::BeforeGraphQL, data: null));

            // Run GraphQL
            $data = Graphql::init();

            $data = Middleware::execute(
                MiddlewareType::HTTP,
                new Data(
                    Http::AfterGraphQL,
                    data: json_decode($data, false, 512, JSON_THROW_ON_ERROR)
                )
            );

            header('Content-Type: application/json');
            echo json_encode($data, JSON_THROW_ON_ERROR);
            exit();
        }

        // TODO load site modules

        // Run before dispatch
        Middleware::execute(MiddlewareType::HTTP, new Data(Http::BeforeRoute, data: null));

        Router::dispatch();
    }

    /**
     *
     * Launch Sail for Cron execution
     *
     * @param string $execPath
     * @return void
     * @throws SiteException
     *
     */
    public static function initForCron(string $execPath)
    {
        static::$workingDirectory = dirname($execPath);

        $securitySettings = [];
        include_once static::$workingDirectory . '/config/security.php';

        // Register the error handler
        static::$errorHandler = new Run();
        $ph = new PlainTextHandler();

        static::$errorHandler->pushHandler($ph);
        static::$errorHandler->register();

        static::bootBasics($securitySettings);
    }

    /**
     *
     * Get the root directory of the system
     *
     * @return string
     *
     */
    public static function getWorkingDirectory(): string
    {
        return static::$workingDirectory;
    }

    /**
     *
     * Get the template directory for the current site
     *
     * @return string
     *
     */
    public static function getTemplateDirectory(): string
    {
        return static::$templateDirectory;
    }

    /**
     *
     * Get the cache directory for the site
     *
     * @return string
     *
     */
    public static function getCacheDirectory(): string
    {
        return static::$cacheDirectory;
    }

    /**
     *
     * Get filesystem directory
     *
     * @return string
     *
     */
    public static function getFSDirectory(): string
    {
        return static::$fsDirectory;
    }

    /**
     *
     * Access the name of the current application
     *
     * @return string
     *
     */
    public static function currentApp(): string
    {
        return static::$currentApp;
    }

    /**
     *
     * Get the error handler
     *
     * @return Run
     *
     */
    public static function getErrorHandler(): Run
    {
        return static::$errorHandler;
    }

    // -------------------------------------------------- Private --------------------------------------------------- //

    private static function bootBasics(array $securitySettings)
    {
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
            throw new SiteException('No site found for the current host, please make sure it\'s not a mistake.', 0500);
        }

        // Load Filesystem
        static::$fsDirectory = static::$workingDirectory . '/storage/fs/' . static::$currentApp;
        Filesystem::mountCore();
        Filesystem::init();

        // Load security into place so it's available everywhere
        Security::init();
        Security::loadSettings($securitySettings);

        // Load configurations
        static::$configDirectory = static::$workingDirectory . '/config/' . static::$currentApp;

        // Load .env file
        $dotenv = Dotenv::createImmutable(static::$workingDirectory, $environments[static::$currentApp]['file']);
        $dotenv->load();

        $config = [];
        include_once static::$configDirectory . '/general.php';
        $settings = new Collection($config);

        $_ENV['SETTINGS'] = $settings->get($_ENV['ENVIRONMENT'] ?? 'dev');

        if ($_ENV['SETTINGS']->get('devMode')) {
            ini_set('display_errors', true);
            error_reporting(E_ALL);
        }

        // Determine the Template directory for the site
        static::$templateDirectory = static::$workingDirectory . '/templates/' . static::$currentApp;
        static::$cacheDirectory = static::$workingDirectory . '/storage/cache/' . static::$currentApp;

        // Register Search Adapters
        Search::registerSystemAdapters();
        Search::init();

        // Initialize the router
        Router::init();

        // Load system containers
        static::loadSystemContainers();

        // Load all site's containers
        static::loadContainers();

        // Ensure peak performance from the database
        static::ensurePerformance();
    }

    /**
     *
     * @throws FileException
     * @throws JsonException
     * @throws Errors\DatabaseException
     *
     */
    private static function loadContainers(): void
    {
        static::loadContainerFromComposer(static::$workingDirectory);
    }

    /**
     *
     * @throws FileException
     * @throws JsonException
     * @throws Errors\DatabaseException
     *
     */
    private static function loadSystemContainers(): void
    {
        static::loadContainerFromComposer(dirname(__DIR__));
    }

    /**
     *
     * @throws Errors\DatabaseException
     * @throws FileException
     * @throws JsonException
     *
     */
    private static function loadContainerFromComposer(string $path): void
    {
        // Read the application level composer.json file
        $composerFile = $path . '/composer.json';
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

                        // Run the search setup
                        $instance->configureSearch();

                        // Run the GraphQL setup
                        $instance->graphql();
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

            if (method_exists($class, 'ensureIndex')) {
                $class::ensureIndexes();
            }
        });
    }
}