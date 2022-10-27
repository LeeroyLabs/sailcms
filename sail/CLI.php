<?php

namespace SailCMS;

use Exception;
use JsonException;
use SailCMS\CLI\Cache;
use SailCMS\CLI\Command;
use SailCMS\CLI\Container;
use SailCMS\CLI\Controller;
use SailCMS\CLI\Install;
use SailCMS\CLI\Model;
use SailCMS\CLI\Version;
use SailCMS\CLI\Module;
use SailCMS\CLI\Queue;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\FileException;
use SailCMS\Errors\SiteException;
use Symfony\Component\Console\Application;

class CLI
{
    public const CLI_VERSION = '1.0.0-next.2';
    private static string $workingDirectory = '';
    public static Collection $registeredCommands;

    public function __construct(string $path)
    {
        static::$workingDirectory = $path;
    }

    /**
     *
     * Get working directory
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
        $application->add(new Model());
        $application->add(new Queue());
        $application->add(new Cache());

        // Custom commands
        if (!isset(static::$registeredCommands)) {
            static::$registeredCommands = new Collection([]);
        }

        foreach (static::$registeredCommands->unwrap() as $commands) {
            foreach ($commands as $command) {
                $application->add(new $command());
            }
        }

        $application->run();
    }
}