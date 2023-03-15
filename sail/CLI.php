<?php

namespace SailCMS;

use Exception;
use JsonException;
use League\Flysystem\FilesystemException;
use SailCMS\CLI\BasicAuth;
use SailCMS\CLI\Cache;
use SailCMS\CLI\Command;
use SailCMS\CLI\Container;
use SailCMS\CLI\Controller;
use SailCMS\CLI\Entry;
use SailCMS\CLI\Install;
use SailCMS\CLI\InstallOfficial;
use SailCMS\CLI\Migrate;
use SailCMS\CLI\Migrations;
use SailCMS\CLI\Model;
use SailCMS\CLI\Module;
use SailCMS\CLI\Password;
use SailCMS\CLI\Queue;
use SailCMS\CLI\Schema;
use SailCMS\CLI\Test;
use SailCMS\CLI\Version;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\FileException;
use SailCMS\Errors\SiteException;
use Symfony\Component\Console\Application;

final class CLI
{
    public const CLI_VERSION = '1.0.0';
    private static string $workingDirectory = '';
    public static Collection $registeredCommands;

    public function __construct(string $path)
    {
        self::$workingDirectory = $path;
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
        return self::$workingDirectory;
    }

    /**
     *
     * Run the CLI
     *
     * @throws FileException
     * @throws SiteException
     * @throws DatabaseException
     * @throws JsonException
     * @throws Exception|
     * @throws FilesystemException
     *
     */
    public function run(): void
    {
        // Load Sail Basics
        Sail::initForCli(self::$workingDirectory);

        $application = new Application();

        // Core commands
        $application->add(new Module());
        $application->add(new Container());
        $application->add(new Controller());
        $application->add(new Version());
        $application->add(new Install());
        $application->add(new Command());
        $application->add(new Model());
        $application->add(new Entry());
        $application->add(new Queue());
        $application->add(new Cache());
        $application->add(new Schema());
        $application->add(new Migrate());
        $application->add(new Migrations());
        $application->add(new Test());
        $application->add(new BasicAuth());
        $application->add(new InstallOfficial());
        $application->add(new Password());

        // Custom commands
        if (!isset(self::$registeredCommands)) {
            self::$registeredCommands = Collection::init();
        }

        foreach (self::$registeredCommands->unwrap() as $commands) {
            foreach ($commands as $command) {
                $application->add(new $command());
            }
        }

        $application->run();
    }
}