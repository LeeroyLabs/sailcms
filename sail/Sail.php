<?php

namespace SailCMS;

include_once dirname(__DIR__) . '/Globals.php';

use Clockwork\Support\Vanilla\Clockwork;
use Dotenv\Dotenv;
use Exception;
use JsonException;
use League\Flysystem\FilesystemException;
use RobThree\Auth\TwoFactorAuthException;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\FileException;
use SailCMS\Errors\GraphqlException;
use SailCMS\Errors\PermissionException;
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
use Whoops\Handler\PlainTextHandler;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;

class Sail
{
    public const SAIL_VERSION = '3.0.0-next.25';
    public const SAIL_MAJOR_VERSION = 3;
    public const SAIL_MINOR_VERSION = 0;
    public const SAIL_REVISION_VERSION = 0;
    public const STATE_WEB = 10001;
    public const STATE_CLI = 10002;

    // Directories
    private static string $workingDirectory = '';
    private static string $configDirectory = '';
    private static string $templateDirectory = '';
    private static string $cacheDirectory = '';
    private static string $fsDirectory = '';

    // Error Handler
    private static Run $errorHandler;

    // Cli flag
    private static bool $isCLI = false;

    // In install mode?
    private static bool $installMode = false;

    private static string $siteID = 'main';

    private static Clockwork $clockwork;

    private static bool $isGraphQL = false;

    public static bool $isServerless = false;

    private static Collection $environmentData;
//    private static bool $encryptedEnv = false; // No usage for that @Marc ?

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
     * @throws \JsonException
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SiteException
     * @throws SodiumException
     * @throws SyntaxError
     * @throws TwoFactorAuthException
     * @throws ACLException
     * @throws \GraphQL\Error\SyntaxError
     * @throws Errors\EntryException
     * @throws PermissionException
     * @throws GraphqlException
     *
     */
    public static function init(string $execPath): void
    {
        self::$workingDirectory = dirname($execPath);
        self::setupEnv();

        $securitySettings = [];
        include_once self::$workingDirectory . '/config/security.php';

        // Register the error handler
        self::$errorHandler = new Run();
        $ct = getallheaders()['Content-Type'] ?? 'text/html';
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

        self::$errorHandler->pushHandler($ph);
        self::$errorHandler->register();

        self::bootBasics($securitySettings);

        // Run Basic Authentication
        if (setting('useBasicAuthentication')) {
            self::handleBasicAuthentication();
        }

        // CORS setup
        self::setupCORS();

        // CSRF Check
        Security::verifyCSRF();

        // 2FA Setup
        self::setup2FA();

        // Headless CSRF Setup
        self::setupHeadlessCSRF();

        if ($_SERVER['REQUEST_URI'] === '/v3/sitelist') {
            self::outputAvailableSites();
        }

        // Execute the boot file (system is available this point)
        if (file_exists(self::$workingDirectory . '/config/boot.php')) {
            require_once self::$workingDirectory . '/config/boot.php';
        }

        $gqlTrigger = '/' . setting('graphql.trigger', 'graphql');
        $gqlActive = setting('graphql.active', true);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SERVER['REQUEST_URI'] === $gqlTrigger && $gqlActive) {
            // Run GraphQL
            self::$isGraphQL = true;
            $data = GraphQL::init();

            if (env('debug', 'off') === 'on') {
                self::$clockwork->requestProcessed();
            }

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($data, JSON_THROW_ON_ERROR);
            exit();
        }

        // Run before dispatch
        Middleware::execute(MiddlewareType::HTTP, new Data(Http::BeforeRoute, data: null));

        if (env('debug', 'off') === 'on') {
            Router::addClockworkSupport();
        }

        if (setting('emails.usePreviewer', false)) {
            Router::setupEmailPreviewer();
        }

        Router::dispatch();
    }

    /**
     *
     * Boot basic systems
     *
     * @params array $securitySettings
     * @params bool $skipContainers
     * @throws DatabaseException
     * @throws JsonException
     * @throws FileException
     * @throws SiteException
     * @throws ACLException
     * @throws PermissionException
     * @throws FilesystemException
     *
     */
    private static function bootBasics(array $securitySettings, bool $skipContainers = false): void
    {
        if (!file_exists(self::$workingDirectory . '/config')) {
            self::$installMode = true;
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

        // Load Sites
        self::loadAndDetectSites();

        // Load Filesystem
        self::$fsDirectory = self::$workingDirectory . '/storage/fs';
        Filesystem::mountCore();
        Filesystem::init();

        // Load security into place so it's available everywhere
        Security::init();

        // Load configurations
        self::$configDirectory = self::$workingDirectory . '/config';

        if (!self::$installMode) {
            $config = self::loadCombinedConfigurations();
        } else {
            $config = include dirname(__DIR__) . '/install/config/general.' . env('ENVIRONMENT', 'dev') . '.php';
        }

        $settings = new Collection($config);
        self::$environmentData->setFor('SETTINGS', $settings);

        if (setting('devMode', false)) {
            ini_set('display_errors', true);
            error_reporting(E_ALL & ~E_WARNING | ~E_DEPRECATED);
        } else {
            ini_set('display_errors', false);
            error_reporting(~E_ALL);
        }

        // Load cms ACLs
        ACL::loadCmsACL();

        // Load cms Fields
        Field::init();

        // Initialize the logger
        Log::init();

        // Determine the Template directory for the site
        self::$templateDirectory = self::$workingDirectory . '/templates/';
        self::$cacheDirectory = self::$workingDirectory . '/storage/cache';

        // Register Search Adapters
        Search::registerSystemAdapters();
        Search::init();

        // Initialize the router
        Router::init();

        // If We are not in the CLI, setup session
        if (!self::$isCLI) {
            Session::manager();
        }

        // Load all site's containers
        if (!$skipContainers) {
            Debug::eventStart('Initialize Containers', 'green');
            self::loadContainerFromComposer(self::$workingDirectory);
            Debug::eventEnd('Initialize Containers');
        }

        // Load all site's modules
        Debug::eventStart('Initialize Modules', 'purple');
        self::loadModulesFromComposer(self::$workingDirectory);
        Debug::eventEnd('Initialize Modules');

        // Authenticate user
        User::authenticate();

        // Ensure peak performance from the database
        self::ensurePerformance();
    }

    /**
     *
     * Set up the .env
     *
     * @return void
     * @throws FileException
     *
     */
    private static function setupEnv(): void
    {
        // Create .env file if it does not exist
        if (!file_exists(self::$workingDirectory . '/.env')) {
            file_put_contents(
                self::$workingDirectory . '/.env',
                file_get_contents(dirname(__DIR__) . '/install/env')
            );
        } else {
            // Load .env file
            try {
                $envData = file_get_contents(self::$workingDirectory . '/.env');
                $env = Dotenv::parse($envData);

                self::$environmentData = new Collection($env);
            } catch (Exception $e) {
                throw new FileException('Cannot read/find the .env file');
            }
        }

        // Setup Clockwork for debugging info
        if (env('debug', 'off') === 'on') {
            $path = self::$workingDirectory . '/storage/debug';

            if (self::$isServerless) {
                if (!file_exists('/tmp/storage')) {
                    try {
                        mkdir('/tmp/storage');
                        $path = '/tmp/storage/debug';
                    } catch (Exception $e) {
                        return;
                    }
                }
            }

            self::$clockwork = Clockwork::init([
                'storage_files_path' => $path,
                'register_helper' => true
            ]);

            register_shutdown_function(self::shutdownHandler(...));
            Debug::eventStart('Running SailCMS', 'blue');
        }
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
                    $instance->init();

                    $info = $instance->info();

                    // Register the container
                    Register::instance()->registerContainer($info, $className);

                    // Install the routes
                    $instance->routes();

                    // Run the search setup
                    $instance->configureSearch();

                    // Run the GraphQL setup
                    $instance->graphql();

                    // Run the Event setup
                    $instance->events();

                    // Run middleware registration
                    $instance->middleware();

                    // Run the ACL registration
                    $acls = $instance->permissions();

                    ACL::loadCustom($acls);

                    // load custom fields
                    Field::loadCustom($instance->fields());

                    // Run the command registration
                    $commands = $instance->cli()->unwrap();

                    if (empty(CLI::$registeredCommands)) {
                        CLI::$registeredCommands = Collection::init();
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

                    // Run the Event setup
                    $instance->events();

                    // Run the command registration
                    $commands = $instance->cli();

                    if (empty(CLI::$registeredCommands)) {
                        CLI::$registeredCommands = Collection::init();
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
     * @throws PermissionException
     * @throws  FilesystemException
     *
     */
    public static function initForCron(string $execPath): void
    {
        self::$workingDirectory = dirname($execPath);
        self::setupEnv();
        self::$isCLI = true;

        $securitySettings = [];
        include_once self::$workingDirectory . '/config/security.php';

        // Register the error handler
        self::$errorHandler = new Run();
        $ph = new PlainTextHandler();

        self::$errorHandler->pushHandler($ph);
        self::$errorHandler->register();

        self::bootBasics($securitySettings);
    }

    /**
     *
     * Launch Sail for CLI execution
     *
     * @param  string  $execPath
     * @return void
     * @throws ACLException
     * @throws DatabaseException
     * @throws FileException
     * @throws FilesystemException
     * @throws JsonException
     * @throws PermissionException
     * @throws SiteException
     *
     */
    public static function initForCli(string $execPath): void
    {
        self::$workingDirectory = $execPath;
        self::setupEnv();
        self::$isCLI = true;

        $securitySettings = [];

        if (file_exists(self::$workingDirectory . '/config')) {
            include_once self::$workingDirectory . '/config/security.php';
        } else {
            $securitySettings = ['envBlacklist' => []];
        }

        // Register the error handler
        self::$errorHandler = new Run();
        $ph = new PlainTextHandler();

        self::$errorHandler->pushHandler($ph);
        self::$errorHandler->register();

        self::bootBasics($securitySettings);
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
        return self::$workingDirectory;
    }

    /**
     *
     * Set the working directory
     *
     * @param  string  $path
     * @return void
     *
     */
    public static function setWorkingDirectory(string $path): void
    {
        self::$workingDirectory = $path;
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
        return self::$templateDirectory . '/';
    }

    /**
     *
     * Force set the template directory
     *
     * @param  string  $path
     * @return void
     *
     */
    public static function setTemplateDirectory(string $path): void
    {
        self::$templateDirectory = $path;
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
        return self::$cacheDirectory;
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
        return self::$fsDirectory;
    }

    /**
     *
     * Are we in the CLI
     *
     * @return bool
     *
     */
    public static function isCLI(): bool
    {
        return self::$isCLI;
    }

    /**
     *
     * Set app state (either web or cli) for some very specific use cases
     * NOTE: DO NOT USE FOR ANYTHING, THIS IS RESERVED FOR UNIT TEST
     *
     * @param  int     $state
     * @param  string  $env
     * @param  string  $forceIOPath
     * @return void
     * @throws DatabaseException
     * @throws FilesystemException
     * @throws FileException
     *
     */
    public static function setAppState(int $state, string $env = '', string $forceIOPath = ''): void
    {
        if ($state === self::STATE_CLI) {
            $_ENV['DEBUG'] = 'off';
            self::$isCLI = true;

            self::$templateDirectory = self::$workingDirectory . '/templates';
            self::$cacheDirectory = self::$workingDirectory . '/storage/cache';

            self::setupEnv();
            
            $config = include self::$workingDirectory . '/config/general.dev.php';
            $settings = new Collection($config);

            if ($env !== '' && isset($config[$env])) {
                self::$environmentData->setFor('SETTINGS', $settings);
            } else {
                self::$environmentData->setFor('SETTINGS', $settings);
            }

            ACL::init();
            Log::init();

            self::loadAndDetectSites();
            Filesystem::mountCore($forceIOPath);
            Filesystem::init();
            return;
        }

        self::$isCLI = false;
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
        return self::$errorHandler;
    }

    /**
     *
     * Get the current Site ID
     *
     * @return string
     *
     */
    public static function siteId(): string
    {
        return self::$siteID ?? 'default';
    }

    /**
     *
     * Handle shutdowns to end the debugger
     *
     * @return void
     *
     */
    public static function shutdownHandler(): void
    {
        Debug::eventEnd('Running SailCMS');

        if (!self::$isGraphQL) {
            self::$clockwork->requestProcessed();
        }
    }

    /**
     *
     * Get the clockwork instance
     *
     * @return Clockwork|null
     *
     */
    public static function getClockWork(): ?Clockwork
    {
        if (env('debug', 'off') === 'on') {
            return self::$clockwork;
        }

        return null;
    }

    /**
     *
     * Run bare minimum for tests to work
     *
     * @param  string  $rootDir
     * @param  string  $templatePath
     * @return void
     * @throws ACLException
     * @throws DatabaseException
     * @throws FileException
     * @throws FilesystemException
     * @throws JsonException
     * @throws PermissionException
     * @throws Exception
     *
     */
    public static function setupForTests(string $rootDir = '', string $templatePath = ''): void
    {
        self::setWorkingDirectory($rootDir . '/mock');
        self::setAppState(self::STATE_CLI, 'dev', $rootDir . '/mock');

        // Load security into place so it's available everywhere
        Security::init();

        // Load cms ACLs
        ACL::loadCmsACL();

        // Register Search Adapters
        Search::registerSystemAdapters();
        Search::init();

        // Load cms Fields
        Field::init();

        // Initialize the logger
        Log::init();

        // Initialize the router
        Router::init();

        // Log an admin user for permissions
        $userEmail = env('TEST_USER_EMAIL');
        $userPwd = env('TEST_USER_PWD');

        if (!$userEmail || !$userPwd) {
            throw new Exception('Improperly configured unit tests: TEST_USER_EMAIL and TEST_USER_PWD in your mock .env file should be set');
        }
        (new User())->login($userEmail, $userPwd);

        self::$environmentData->setFor('SITE_URL', 'http://localhost:8888');

        if ($templatePath !== '') {
            self::setTemplateDirectory($templatePath);
        } else {
            self::setTemplateDirectory(dirname($rootDir) . '/templates');
        }

        self::loadContainerFromComposer(dirname($rootDir));
        self::loadModulesFromComposer(dirname($rootDir));
    }

    /**
     *
     * Get an environment variable in safe way (cannot be dumped)
     *
     * @param  string  $key
     * @return mixed
     *
     */
    public static function getEnvironmentVariable(string $key): mixed
    {
        return self::$environmentData->get($key, null);
    }

    /**
     *
     * Compare requested version compatibility with current version of sail
     *
     * @param  string  $version
     * @return int
     *
     */
    public static function verifyCompatibility(string $version): int
    {
        // First 2 chars is the operator
        $operator = substr($version, 0, 2);
        $valid = ['<=', '!<', '>=', '!>'];

        if (!in_array($operator, $valid)) {
            return -1;
        }

        // Breakdown version
        $version = substr($version, 2);
        $sailVersion = Sail::SAIL_MAJOR_VERSION . '.' . Sail::SAIL_MINOR_VERSION . '.' . Sail::SAIL_REVISION_VERSION;

        if ($operator === '!<') {
            $operator = '>';
        } elseif ($operator === '!>') {
            $operator = '<';
        }

        if (version_compare($sailVersion, $version, $operator)) {
            return 1;
        }

        return 0;
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
        $cors = setting('cors', ['use' => false, 'origins' => '*', 'allowCredentials' => false]);

        if (setting('cors.use', false)) {
            $origin = setting('cors.origin', '*');
            $maxAge = setting('cors.maxAge', 86_400);

            header("Access-Control-Allow-Origin: {$origin}");
            header("Access-Control-Max-Age: {$maxAge}");

            if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
                $methods = implode(",", setting('cors.methods', new Collection(['get']))->unwrap());
                $headers = implode(",", setting('cors.headers', Collection::init())->unwrap());

                // Force these to exist
                if ($headers !== '') {
                    $headers .= ',';
                }

                $headers .= 'Accept,Upgrade-Insecure-Requests,Content-Type,x-requested-with,x-access-token,x-domain-override';

                header("Access-Control-Allow-Methods: {$methods}");
                header("Access-Control-Allow-Headers: {$headers}");
                header('Content-Length: 0');
                header('Content-Type: text/plain');
                die(''); // Options just needs headers, the rest is not required. Stop now!
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

            $whitelist = explode(',', setting('tfa.whitelist', Collection::init())->unwrap());
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

    /**
     *
     * Detect what site is running
     *
     * @return void
     * @throws FilesystemException
     *
     */
    private static function loadAndDetectSites(): void
    {
        $defaultLocale = "en";
        if (file_exists(self::$workingDirectory . '/config/sites.php')) {
            $sites = include self::$workingDirectory . '/config/sites.php';

            foreach ($sites as $name => $config) {
                if (isset($_SERVER['HTTP_HOST'])) {
                    $host = explode(':', $_SERVER['HTTP_HOST'])[0];
                } else {
                    $host = explode(':', env('site_url', 'http://localhost'))[0];
                }

                if (in_array($host, $config['urls'], true) || in_array('*', $config['urls'], true)) {
                    self::$siteID = $name;
                    $defaultLocale = $config['defaultLocale'];
                    Locale::setAvailableLocales($config['locales']);
                    Locale::setCurrent($defaultLocale);
                    Locale::setDefault($defaultLocale);
                    break;
                }
            }

            // Let the header 'x-site-id' override the value
            $headers = getallheaders();

            foreach ($headers as $key => $value) {
                if (strtolower($key) === 'x-site-id') {
                    self::$siteID = $value;
                    Locale::setAvailableLocales($sites[$value]['locales']);
                    Locale::setCurrent($sites[$value]['defaultLocale']);
                    Locale::setDefault($defaultLocale);
                    break;
                }
            }
        } else {
            self::$siteID = 'default';
            Locale::setAvailableLocales([$defaultLocale]);
            Locale::setCurrent($defaultLocale);
            Locale::setDefault($defaultLocale);
        }
    }

    /**
     *
     * Output the available site ids
     *
     * @return void
     * @throws JsonException
     *
     */
    private static function outputAvailableSites(): void
    {
        $sites = include self::$workingDirectory . '/config/sites.php';
        $names = [];

        foreach ($sites as $key => $value) {
            $names[] = $key;
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($names, JSON_THROW_ON_ERROR);
        exit();
    }

    /**
     *
     * Handle the Basic Authentication feature
     *
     * @return void
     *
     */
    private static function handleBasicAuthentication(): void
    {
        $u = $_SERVER['PHP_AUTH_USER'] ?? '';
        $p = $_SERVER['PHP_AUTH_PW'] ?? '';

        // Validate if not empty
        $userpasses = file_get_contents(self::$workingDirectory . '/storage/fs/vault/basic_auth');
        $pairs = explode(PHP_EOL, $userpasses);

        foreach ($pairs as $pair) {
            [$user, $pass] = explode(':', $pair);
            $validPass = Security::verifyPassword($p, $pass);

            if ($u !== $user || !$validPass) {
                $u = '';
                $p = '';
            }
        }

        if ($u === '' && $p === '') {
            header('WWW-Authenticate: Basic realm="Secure Area"');
            header('HTTP/1.0 401 Unauthorized');
        }
    }

    /**
     *
     * Combine the default configs with the overwriting configs
     *
     * @return array
     *
     */
    private static function loadCombinedConfigurations(): array
    {
        // Default configuration
        $defaultConfig = include dirname(__DIR__) . '/config/general.' . env('ENVIRONMENT', 'dev') . '.php';

        // App Configuration
        $config = include self::$configDirectory . '/general.' . env('ENVIRONMENT', 'dev') . '.php';

        return self::processConfigs($defaultConfig, $config);
    }

    /**
     *
     * Process the configs to see what should be replaced
     *
     * @param  array  $default
     * @param  array  $config
     * @return array
     *
     */
    private static function processConfigs(array $default, array $config): array
    {
        $processed = [];

        foreach ($default as $key => $group) {
            if (isset($config[$key])) {
                if (is_scalar($config[$key])) {
                    $processed[$key] = $config[$key];
                } else {
                    $processed[$key] = array_replace($group, $config[$key]);
                }
            } else {
                $processed[$key] = $group;
            }
        }

        return $processed;
    }
}