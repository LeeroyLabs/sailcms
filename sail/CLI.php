<?php

namespace SailCMS;

use Exception;
use JsonException;
use League\Flysystem\FilesystemException;
use splitbrain\phpcli\CLI as PHPCLI;
use splitbrain\phpcli\Colors;
use splitbrain\phpcli\Options;

class CLI extends PHPCLI
{
    private static string $workingDirectory = '';

    public function __construct(string $path)
    {
        parent::__construct();
        static::$workingDirectory = $path;
    }

    protected function setup(Options $options)
    {
        // Load Sail Basics
        Sail::initForCli(static::$workingDirectory);

        // Base Commands
        $options->registerCommand('version', 'Print the version of this tool');
        //$options->registerCommand('help', 'Print out documentation about this tool');

        // Installer
        $options->registerCommand('run/install', 'Install SailCMS');

        // Extend SailCMS
        $options->registerCommand('create/container', 'Create a custom container (features, routes, rest api, graphql, etc.)');
        $options->registerCommand('create/module', 'Create a custom module (features only)');
        $options->registerCommand('create/controller', 'Create a custom controller for a specific container');

        // Container name
        $options->registerArgument('name', 'Name of the container. For example: MyContainer', true, 'create/container');

        // Module name
        $options->registerArgument('name', 'Name of the module. For example: MyModule', true, 'create/module');

        // Controller name
        $options->registerArgument('container', 'Container directory name to create the controller in', true, 'create/controller');
        $options->registerArgument('name', 'Name of the controller. For example: MyCtrlr', true, 'create/controller');
    }

    /**
     *
     * @throws FilesystemException
     * @throws JsonException
     *
     */
    protected function main(Options $options)
    {
        $options->parseOptions();
        $cmd = $options->getCmd();
        $args = $options->getArgs();

        switch ($cmd) {
            case 'version':
                $os = (PHP_OS === 'Darwin') ? 'MacOS' : PHP_OS;

                if ($os !== 'MacOS') {
                    $os = (stripos(PHP_OS_FAMILY, 'WIN') === 0) ? 'Windows' : PHP_OS_FAMILY;
                }

                $this->info("cli 3.0.0");
                $this->info("php " . PHP_VERSION . " (" . $os . ")");
                break;

            case 'run/install':
                $this->install();
                break;

            case 'create/container':
                $this->createContainers($args);
                break;

            case 'create/module':
                $this->createModules($args);
                break;

            case 'create/controller':
                $this->createControllers($args);
                break;

            default:
            case "help":
                echo $options->help();
                break;
        }
    }

    private function install(): void
    {
        $this->testFlight(true);

        // Folders to create
        $folders = [
            'templates',
            'templates/default',
            'web',
            'web/public',
            'web/public/default',
            'web/public/default/images',
            'web/public/default/css',
            'web/public/default/js',
            'web/public/default/fonts',
            'storage',
            'storage/fs',
            'storage/fs/default',
            'storage/fs/default/uploads',
            'storage/cache/',
            'storage/vault',
            'storage/vault/default',
            'storage/cache/default',
            'config',
            'config/default',
            'modules',
            'containers',
            'locales',
            'locales/default'
        ];

        // Files to create (dest => src)
        $files = [
            'web/index.php' => 'index.php',
            'web/.htaccess' => 'htaccess',
            'web/default/images/.gitkeep' => '',
            'web/default/css/.gitkeep' => '',
            'web/default/js/.gitkeep' => '',
            'web/default/fonts/.gitkeep' => '',
            'config/default/general.php' => 'config/general.php',
            'config/apps.env.php' => 'env.php',
            'modules/.gitkeep' => '',
            'templates/default/.gitkeep' => '',
            '.env.default' => 'env.default',
            'storage/fs/default/upload/.gitkeep' => '',
            'storage/cache/default/.gitkeep' => '',
            'locales/default/en.yaml'
        ];

        $this->notice($this->colors->wrap("Installing SailCMS v3.0.0...", Colors::C_BLUE));

        foreach ($folders as $folder) {
            if (!mkdir($concurrentDirectory = static::$workingDirectory . '/' . $folder) && !is_dir($concurrentDirectory)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
            }
        }

        $workPath = dirname(__DIR__) . '/install/';

        foreach ($files as $key => $file) {
            if (str_contains($key, 'gitkeep')) {
                touch(static::$workingDirectory . '/' . $key);
            } else {
                file_put_contents(static::$workingDirectory . '/' . $key, file_get_contents($workPath . $file));
            }
        }

        // Generate Security Key file
        $this->notice($this->colors->wrap('  Generating Security Key...', Colors::C_BLUE));
        static::$cli->Security::init();

        // Generate Admin user
        $this->notice($this->colors->wrap('  Generating Admin user...', Colors::C_BLUE));
        // TODO : FINISH THIS
    }

    /**
     *
     * Create a containers
     *
     * @throws FilesystemException
     * @throws JsonException
     *
     */
    private function createContainers(array $args)
    {
        // Make sure all that is required is set and available
        $this->testFlight();
        $fs = Filesystem::manager();

        foreach ($args as $num => $name) {
            if ($num > 0) {
                echo "\n";
            }

            $this->notice("Creating Container {$name}...");

            $name = ucfirst(Text::camelCase(Text::deburr($name)));
            $path = "root://containers/{$name}";

            if (!$fs->directoryExists($path)) {
                // Create module directory
                $fs->createDirectory($path);

                // Create module file
                $mod = $fs->read('install://container.php');
                $mod = str_replace('[NAME]', $name, $mod);
                $fs->write($path . '/Container.php', $mod);

                // Create middleware file
                $mod = $fs->read('install://middleware.php');
                $mod = str_replace('[NAME]', $name, $mod);
                $fs->write($path . '/Middleware.php', $mod);

                // Create controller directory
                $fs->createDirectory($path . '/Controllers');

                $this->success("Created {$name} container Successfully!");
                $this->notice("Adding {$name} to autoload...");

                // Install the configuration required in composer.json
                $json = json_decode($fs->read('root://composer.json'), false, 10, JSON_THROW_ON_ERROR);
                $json->sailcms->containers = [...$json->sailcms->containers, $name];
                $json->autoload->{'psr-4'}->{$name . '\\'} = 'containers/' . $name;

                $encoded = json_encode($json, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $fs->write('root://composer.json', $encoded);

                // Dump autoload and regenerate
                $this->executeComposerRefresh();

                $this->success("Container {$name} is installed and ready to go! 🚀");
            } else {
                $this->fatal("Container directory already exists, please make sure it's not a mistake.");
                die();
            }
        }
    }

    /**
     *
     * Create modules
     *
     * @throws FilesystemException
     * @throws JsonException
     *
     */
    private function createModules(array $args): void
    {
        // Make sure all that is required is set and available
        $this->testFlight();
        $fs = Filesystem::manager();

        foreach ($args as $num => $module) {
            if ($num > 0) {
                echo "\n";
            }

            $this->notice("Creating Module {$module}...");

            $name = ucfirst(Text::camelCase(Text::deburr($module)));
            $path = "root://modules/{$name}";

            if (!$fs->directoryExists($path)) {
                // Create module directory
                $fs->createDirectory($path);

                // Create module file
                $mod = $fs->read('install://module.php');
                $mod = str_replace('[NAME]', $name, $mod);
                $fs->write($path . '/Module.php', $mod);

                // Create middleware file
                $mod = $fs->read('install://middleware.php');
                $mod = str_replace('[NAME]', $name, $mod);
                $fs->write($path . '/Middleware.php', $mod);

                $this->success("Created {$name} module Successfully!");
                $this->notice("Adding {$module} to autoload...");

                // Install the configuration required in composer.json
                $json = json_decode($fs->read('root://composer.json'), false, 10, JSON_THROW_ON_ERROR);
                $json->sailcms->modules = [...$json->sailcms->modules, $name];
                $json->autoload->{'psr-4'}->{$name . '\\'} = 'modules/' . $name;

                $encoded = json_encode($json, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $fs->write('root://composer.json', $encoded);

                // Dump autoload and regenerate
                $this->executeComposerRefresh();

                $this->success("Module {$name} is installed and ready to go! 🚀");
            } else {
                $this->fatal("Module directory already exists, please make sure it's not a mistake.");
                die();
            }
        }
    }

    /**
     *
     * @throws FilesystemException
     *
     */
    private function createControllers(array $args)
    {
        // Make sure all that is required is set and available
        $this->testFlight();
        $fs = Filesystem::manager();

        // Only catch the first 2, rest is ignored
        [$container, $name] = $args;

        $container = ucfirst(Text::camelCase(Text::deburr($container)));
        $path = "root://containers/{$container}";

        $name = ucfirst(Text::camelCase(Text::deburr($name)));

        if ($fs->directoryExists($path)) {
            $this->notice("Creating Controller {$name}...");

            if (!$fs->directoryExists($path . '/Controllers')) {
                // Create controller directory
                $fs->createDirectory($path . '/Controllers');
            }

            $ctrl = $fs->read('install://controller.php');
            $ctrl = str_replace(['[CONTAINER]', '[NAME]'], [$container, $name], $ctrl);

            $fs->write($path . '/Controllers/' . $name . '.php', $ctrl);
            $this->success("Controller {$name} has been created and is ready to go! 🚀");
        } else {
            $this->fatal("Cannot create controller {$name} because the container '{$container}' does not seem to exist.");
            die();
        }
    }

    private function executeComposerRefresh(): void
    {
        exec($_ENV['COMPOSER_LOCATION'] . ' dump-autoload -a');
    }

    private function testFlight(bool $skipFolders = false): void
    {
        if (empty($_ENV['COMPOSER_LOCATION'])) {
            $this->fatal(new Exception('Composer location not found in environment file, please make sure it\'s set'));
            die();
        }

        if ($skipFolders) {
            return;
        }

        $fs = Filesystem::manager();

        if ($fs->visibility('root://modules') !== 'public') {
            $this->fatal(new Exception('SailCMS is not allowed to write in the modules directory'));
            die();
        }

        if ($fs->visibility('local://') !== 'public') {
            $path = '/storage/fs/' . Sail::currentApp();
            $this->fatal(new Exception("SailCMS is not allowed to write in the '{$path}' directory"));
            die();
        }

        if ($fs->visibility('cache://') !== 'public') {
            $path = '/storage/cache/' . Sail::currentApp();
            $this->fatal(new Exception("SailCMS is not allowed to write in the '{$path}' directory"));
            die();
        }
    }
}