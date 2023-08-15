<?php

namespace SailCMS\CLI;

use League\Flysystem\FilesystemException;
use SailCMS\Internal\Filesystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Test extends Command
{
    protected static $defaultDescription = 'Create a new test suite';
    protected static $defaultName = 'create:test';

    /**
     *
     * Run the command
     *
     * @param  InputInterface   $input
     * @param  OutputInterface  $output
     * @return int
     * @throws FilesystemException
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        Tools::testFlight();
        $fs = Filesystem::manager();

        $name = strtolower($input->getArgument('name'));

        Tools::outputInfo('creating', "Creating test [b]{$name}[/b]");

        $path = 'root://tests';

        if (!$fs->directoryExists($path)) {
            $fs->createDirectory($path);
        }

        // Make sure the mock folder exists
        if (!$fs->directoryExists($path . '/mock')) {
            $mockPath = $path . '/mock';
            $xmlContent = $fs->read('install://test.xml');

            $fs->createDirectory($mockPath);
            $fs->createDirectory($mockPath . '/asset');
            $fs->createDirectory($mockPath . '/config');
            $fs->createDirectory($mockPath . '/locales');
            $fs->createDirectory($mockPath . '/storage');
            $fs->createDirectory($mockPath . '/templates');
            $fs->createDirectory($mockPath . '/uploads');
            $fs->copy('install://env', $mockPath . '/.env');
            $fs->copy('install://db-test.php', $mockPath . '/db.php');
            $fs->write($mockPath . '/locales/en.yaml', '');
            $fs->createDirectory($mockPath . '/storage/cache');
            $fs->createDirectory($mockPath . '/storage/debug');
            $fs->createDirectory($mockPath . '/storage/fs');
            $fs->createDirectory($mockPath . '/storage/fs/logs');
            $fs->createDirectory($mockPath . '/storage/fs/vault');
            $fs->write('root://phpunit.xml', $xmlContent);
        }

        // Create test
        $testTpl = $fs->read('install://test.php');
        $fs->write($path . '/' . $name . 'Test.php', $testTpl);

        Tools::outputInfo("created", "Created test [b]{$name}[/b] 	🤖", 'bg-green-500');
        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this->setHelp("Create a new test suite");
        $this->addArgument('name', InputArgument::REQUIRED, 'Name of the test');
    }
}