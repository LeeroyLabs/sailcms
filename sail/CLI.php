<?php

namespace SailCMS;

use Exception;
use JsonException;
use SailCMS\Cli\Command;
use SailCMS\Cli\Container;
use SailCMS\Cli\Controller;
use SailCMS\Cli\Install;
use SailCMS\Cli\Version;
use SailCMS\Cli\Module;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\FileException;
use SailCMS\Errors\SiteException;
use Symfony\Component\Console\Application;

class CLI
{
    public const CLI_VERSION = '1.0.0-next.1';
    private static string $workingDirectory = '';
    public static array $registeredCommands = [];

    public function __construct(string $path)
    {
        static::$workingDirectory = $path;
    }

    /**
     *
     * Run the CLI
     *
     * @throws FileException
     * @throws SiteException
     * @throws DatabaseException
     * @throws JsonException
     * @throws Exception
     *
     */
    public function run(): void
    {
        // Load Sail Basics
        Sail::initForCli(static::$workingDirectory);

        $application = new Application();

        // Core commands
        $application->add(new Module());
        $application->add(new Container());
        $application->add(new Controller());
        $application->add(new Version());
        $application->add(new Install());
        $application->add(new Command());

        // Custom commands
        foreach (static::$registeredCommands as $commands) {
            foreach ($commands as $command) {
                $application->add(new $command());
            }
        }

        $application->run();
    }
}