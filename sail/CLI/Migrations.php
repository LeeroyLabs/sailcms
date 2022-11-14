<?php

namespace SailCMS\CLI;

use SailCMS\CLI;
use SailCMS\Filesystem;
use SailCMS\Sail;
use SailCMS\Text;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Migrations extends Command
{
    protected static $defaultDescription = 'Create a new migration file';
    protected static $defaultName = 'create:migration';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        Tools::testFlight();
        $fs = Filesystem::manager();

        Tools::outputInfo('create', "Creating Migration");

        // Generate directory first
        if (!$fs->directoryExists('root://migrations')) {
            $fs->createDirectory('root://migrations');
            $fs->write('root://migrations/.migrations.manifest', '{"last_run": 0, "currentVersion": 0}');
        }

        $existing = count(glob(Sail::getWorkingDirectory() . '/migrations/Migration_*.php')) + 1;
        $next = str_pad($existing, 4, '0', STR_PAD_LEFT);
        $name = 'Migration_' . $next;
        $file = $name . '.php';

        $migration = $fs->read('install://migration.php');
        $migration = str_replace('[NAME]', $name, $migration);

        $fs->write('root://migrations/' . $file, $migration);
        Tools::outputInfo('created', "Migration '{$name}' has been created and is ready to go! 🚀", 'bg-green-500');

        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this->setHelp("Create a new migration file");
    }
}