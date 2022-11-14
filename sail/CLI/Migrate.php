<?php

namespace SailCMS\CLI;

use JsonException;
use League\Flysystem\FilesystemException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Models\Config;
use SailCMS\Sail;
use SodiumException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Migrate extends Command
{
    protected static $defaultDescription = 'Run database migrations';
    protected static $defaultName = 'db:migrate';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $opt = $input->getArgument('option');

        if ($opt[0] === 'rollback') {
            return $this->rundDown($opts[1] ?? -1);
        } elseif ($opt[0] === 'seed') {
            return $this->runUp(true);
        } else {
            return $this->runUp(false);
        }
    }

    protected function configure(): void
    {
        $this->setHelp("Run database migrations");
        $this->addArgument('option', InputOption::VALUE_OPTIONAL, 'Available options: rollback and seed', ['up']);
    }

    /**
     *
     * Get current version, if not found, set to 0
     *
     * @return int
     * @throws DatabaseException
     * @throws FilesystemException
     * @throws JsonException
     * @throws SodiumException
     *
     */
    private function getCurrentVersion(): int
    {
        $config = Config::getByName('migration');

        if (!$config) {
            return 0;
        }

        return $config->config->version;
    }

    /**
     *
     * Write the current version to db
     *
     * @param  int  $version
     * @return void
     * @throws JsonException
     * @throws FilesystemException
     * @throws DatabaseException
     * @throws SodiumException
     *
     */
    private function writeCurrentVerison(int $version): void
    {
        Config::setByName('migration', ['version' => $version], false);
    }

    /**
     *
     * Run the UP methods of all migrations after current version
     *
     * @param  bool  $seed
     * @return int
     * @throws JsonException
     * @throws FilesystemException
     * @throws DatabaseException
     * @throws SodiumException
     *
     */
    private function runUp(bool $seed = false): int
    {
        Tools::outputInfo('running', "Running migrations");

        $currentVersion = ($seed) ? 0 : $this->getCurrentVersion();
        $files = glob(Sail::getWorkingDirectory() . '/migrations/Migration_*.php');
        $executedFiles = [];
        $last = 0;

        foreach ($files as $file) {
            $parts = explode('_', $file);
            $version = (int)str_replace('.php', '', $parts[count($parts) - 1]);

            if ($version > $currentVersion) {
                $executedFiles[] = $file;
                $last = $version;
            }
        }

        natsort($executedFiles);
        $diff = $last - $currentVersion;

        if ($diff <= 0) {
            Tools::outputInfo('done', "Database is up to date");
            return Command::SUCCESS;
        }

        Tools::outputInfo('found', "Codebase is {$diff} versions behind, starting...");

        foreach ($executedFiles as $file) {
            include_once $file;

            $class = str_replace('.php', '', basename($file));
            $instance = new $class();

            Tools::outputInfo('migrate', "Running migration from [b]{$class}[/b]");
            $instance->up();
        }

        $this->writeCurrentVerison($last);
        Tools::outputInfo('status', "Current version is {$last}", 'bg-green-500');
        Tools::outputInfo('done', "Database is up to date! 💪", 'bg-green-500');
        return Command::SUCCESS;
    }

    private function runDown(int $version = -1): int
    {
        $currentVersion = $this->getCurrentVersion();

        if ($currentVersion > 0) {
            $version = $currentVersion - 1;
        } else {
            Tools::outputInfo('error', "Cannot rollback, version is at 0" . 'bg-red-500');
            return Command::FAILURE;
        }

        Tools::outputInfo('running', "Rolling back on migrations to version {$version}");
        return Command::SUCCESS;
    }
}