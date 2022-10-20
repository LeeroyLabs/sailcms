<?php

namespace SailCMS;

use Dotenv\Dotenv;
use Exception;
use JsonException;
use League\Flysystem\FilesystemException;
use RobThree\Auth\TwoFactorAuthException;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\FileException;
use SailCMS\Errors\SiteException;
use SailCMS\Http\Request;
use SailCMS\Http\Response;
use SailCMS\Middleware\Data;
use SailCMS\Middleware\Http;
use SailCMS\Models\User;
use SailCMS\Routing\Router;
use SailCMS\Security\TwoFactorAuthenticationController;
use SailCMS\Types\MiddlewareType;
use SodiumException;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Whoops\Handler\JsonResponseHandler;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Handler\PlainTextHandler;
use Whoops\Run;

class Sail
{
    public const SAIL_VERSION = '3.0.0-next.1';

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

    private static bool $isCLI = false;

    private static bool $installMode = false;

    /**
     *
     * Initialize the CMS
     *
     * @param  string  $execPath
     * @return void
     * @throws DatabaseException
     * @throws Errors\RouteReturnException
     * @throws FileException
     * @throws FilesystemException
     * @throws JsonException
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SiteException
     * @throws SodiumException
     * @throws SyntaxError
     * @throws TwoFactorAuthException
     * @throws ACLException
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
            foreach ($securitySettings['envBlacklist'] as $value) {
                $ph->blacklist('_ENV', $value);
                $ph->blacklist('_SERVER', $value);
            }
        }

        static::$errorHandler->pushHandler($ph);
        static::$errorHandler->register();

        static::bootBasics($securitySettings);

        // CORS setup
        static::setupCORS();

        // CSRF Check
        Security::verifyCSRF();

        // 2FA Setup
        static::setup2FA();

        // Headless CSRF Setup
        static::setupHeadlessCSRF();

        if ($_SERVER['REQUEST_URI'] === '/' . $_ENV['SETTINGS']->get('graphql.trigger') && $_ENV['SETTINGS']->get('graphql.active')) {
            // Run GraphQL
            $data = Graphql::init();

            header('Content-Type: application/json');
            echo json_encode($data, JSON_THROW_ON_ERROR);
            exit();
        }

        // Run before dispatch
        Middleware::execute(MiddlewareType::HTTP, new Data(Http::BeforeRoute, data: null));

        Router::dispatch();
    }

    /**
     *
     * @params array $securitySettings
     * @params bool $skipContainers
     * @throws DatabaseException
     * @throws JsonException
     * @throws FileException
     * @throws SiteException
     * @throws ACLException
     *
     */
    private static function bootBasics(array $securitySettings, bool $skipContainers = false): void
    {
        if (!file_exists(static::$workingDirectory . '/config')) {
            static::$installMode = true;
        }

        // Initialize the ACLs
        ACL::init();

        // Detect what site we are on
        $environments = [];

        if (empty($_SERVER['HTTP_HOST'])) {
            $host = 'cli';

            $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['REQUEST_URI'] = '/';
            $_SERVER['HTTP_USER_AGENT'] = 'Chrome';
        }

        // Load Filesystem
        static::$fsDirectory = static::$workingDirectory . '/storage/fs';
        Filesystem::mountCore();
        Filesystem::init();

        // Load security into place so it's available everywhere
        Security::init();
        Security::loadSettings($securitySettings);

        // Load configurations
        static::$configDirectory = static::$workingDirectory . '/config';

        // Load .env file
        $dotenv = Dotenv::createImmutable(static::$workingDirectory, '.env');
        $dotenv->load();

        $config = [];

        if (!static::$installMode) {
            include_once static::$configDirectory . '/general.php';
        } else {
            include_once dirname(__DIR__) . '/install/config/general.php';
        }

        $settings = new Collection($config);

        $_ENV['SETTINGS'] = $settings->get($_ENV['ENVIRONMENT'] ?? 'dev');

        if ($_ENV['SETTINGS']->get('devMode')) {
            ini_set('display_errors', true);
            error_reporting(E_ALL);
        }

        // Initialize the logger
        Log::init();

        // Determine the Template directory for the site
        static::$templateDirectory = static::$workingDirectory . '/templates/';
        static::$cacheDirectory = static::$workingDirectory . '/storage/cache';

        // Register Search Adapters
        Search::registerSystemAdapters();
        Search::init();

        // Initialize the router
        Router::init();

        // If We are not in the CLI, setup session
        if (!static::$isCLI) {
            Session::manager();
        }

        // Authenticate user
        User::authenticate();

        // Load system containers
        static::loadContainerFromComposer(dirname(__DIR__));

        // Load all site's containers
        if (!$skipContainers) {
            static::loadContainerFromComposer(static::$workingDirectory);
        }

        // Load all site's modules
        static::loadModulesFromComposer(static::$workingDirectory);

        // Ensure peak performance from the database
        static::ensurePerformance();
    }

    /**
     *
     * @throws Errors\DatabaseException
     * @throws FileException
     * @throws JsonException
     * @throws Errors\ACLException
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

                    // Install the routes
                    $instance->routes();

                    // Run the search setup
                    $instance->configureSearch();

                    // Run the GraphQL setup
                    $instance->graphql();

                    // Run middleware registration
                    $instance->middleware();

                    // Run the ACL registration
                    $acls = $instance->permissions();

                    ACL::loadCustom($acls);

                    // Run the command registration
                    $commands = $instance->cli()->unwrap();

                    if (empty(CLI::$registeredCommands)) {
                        CLI::$registeredCommands = new Collection([]);
                    }

                    CLI::$registeredCommands->pushSpread($commands);
                } else {
                    throw new FileException("Container {$className} does not exist or is not named properly. Please verify and try again.", 0404);
                }
            }
        }
    }

    /**
     *
     * @throws Errors\DatabaseException
     * @throws FileException
     * @throws JsonException
     *
     */
    private static function loadModulesFromComposer(string $path): void
    {
        // Read the application level composer.json file
        $composerFile = $path . '/composer.json';
        $composer = json_decode(file_get_contents($composerFile), false, 512, JSON_THROW_ON_ERROR);

        if ($composer->sailcms && $composer->sailcms->modules) {
            foreach ($composer->sailcms->modules as $moduleName) {
                $className = $moduleName . '\\Module';

                if (class_exists($className)) {
                    $instance = new $className();
                    $info = $instance->info();

                    // Register only if the site should load it

                    Register::instance()->registerModule($info, $instance, $moduleName);

                    // Run middleware registration
                    $instance->middleware();

                    // Run the command registration
                    $commands = $instance->cli();

                    if (empty(CLI::$registeredCommands)) {
                        CLI::$registeredCommands = new Collection([]);
                    }

                    CLI::$registeredCommands->pushSpread($commands);
                } else {
                    throw new FileException("Module {$className} does not exist or is not named properly. Please verify and try again.", 0404);
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

    /**
     *
     * Launch Sail for Cron execution
     *
     * @param  string  $execPath
     * @return void
     * @throws SiteException
     * @throws JsonException
     * @throws DatabaseException
     * @throws FileException
     * @throws ACLException
     *
     */
    public static function initForCron(string $execPath): void
    {
        static::$workingDirectory = dirname($execPath);
        static::$isCLI = true;

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
     * Launch Sail for CLI execution
     *
     * @param  string  $execPath
     * @return void
     * @throws DatabaseException
     * @throws FileException
     * @throws JsonException
     * @throws SiteException
     * @throws ACLException
     *
     */
    public static function initForCli(string $execPath): void
    {
        static::$workingDirectory = $execPath;
        static::$isCLI = true;

        $securitySettings = [];

        if (file_exists(static::$workingDirectory . '/config')) {
            include_once static::$workingDirectory . '/config/security.php';
        } else {
            $securitySettings = ['envBlacklist' => []];
        }

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
        return static::$templateDirectory . '/';
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
     * Get the error handler
     *
     * @return Run
     *
     */
    public static function getErrorHandler(): Run
    {
        return static::$errorHandler;
    }

    /**
     *
     * Setup CORS headers
     *
     * @return void
     *
     */
    private static function setupCORS(): void
    {
        $cors = $_ENV['SETTINGS']->get('cors');

        if ($cors->get('use')) {
            $origins = implode(',', $cors->get('origins')->unwrap());
            $creds = ($cors->get('allowCredentials')) ? 'true' : 'false';

            header("Access-Control-Allow-Origin: {$origins}");
            header("Access-Control-Allow-Credentials: {$creds}");
            header("Access-Control-Max-Age: {$cors->get('maxAge')}");

            if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
                $methods = implode(",", $cors->get('methods')->unwrap());
                $headers = implode(",", $cors->get('headers')->unwrap());

                header("Access-Control-Allow-Methods: {$methods}");
                header("Access-Control-Allow-Headers: {$headers}");
                header('Content-Length: 0');
                header('Content-Type: text/plain');
                die(); // Options just needs headers, the rest is not required. Stop now!
            }
        }
    }

    /**
     *
     * Setup 2FA system
     *
     * @return void
     * @throws DatabaseException
     * @throws FileException
     * @throws FilesystemException
     * @throws JsonException
     * @throws SodiumException
     * @throws TwoFactorAuthException
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     *
     */
    private static function setup2FA(): void
    {
        if (stripos($_SERVER['REQUEST_URI'], '/v1/tfa') === 0) {
            if ($_SERVER['REQUEST_URI'] === '/v1/tfa' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                $req = new Request();

                // Fail if one or both are missing
                if (empty($req->post('uid')) || empty($req->post('code'))) {
                    header('HTTP/1.0 400 Bad Request');
                    die();
                }

                $tfa = new TwoFactorAuthenticationController($req->post('uid'));
                $tfa->validate($req->post('code'));
                $tfa->render();
                exit();
            }

            $whitelist = explode(',', $_ENV['SETTINGS']->get('tfa.whitelist'));
            $url = parse_url($_SERVER['HTTP_REFERER']);

            if (in_array($url['host'], $whitelist, true)) {
                $parts = explode('/', $_SERVER['REQUEST_URI']);

                Locale::setCurrent($parts[3]);
                $tfa = new TwoFactorAuthenticationController($parts[4]);
                $tfa->render();
                exit();
            }

            header('HTTP/1.0 403 Forbidden');
            die();
        }
    }

    /**
     *
     * Setup Headless CSRF
     *
     * @return void
     * @throws FileException
     * @throws FilesystemException
     * @throws JsonException
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Exception
     *
     */
    private static function setupHeadlessCSRF(): void
    {
        if (stripos($_SERVER['REQUEST_URI'], '/v1/csrf') === 0) {
            $token = Security::csrf();

            $response = Response::json();
            $response->set('token', $token);
            $response->render(false);
            die();
        }
    }
}