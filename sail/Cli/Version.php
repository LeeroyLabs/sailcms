<?php

namespace SailCMS\Cli;

use SailCMS\CLI;
use SailCMS\Sail;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Version extends Command
{
    protected static $defaultDescription = 'Output the current version of the CLI, SailCMS and PHP';
    protected static $defaultName = 'version';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $os = (PHP_OS === 'Darwin') ? 'MacOS' : PHP_OS;

        if ($os !== 'MacOS') {
            $os = (stripos(PHP_OS_FAMILY, 'WIN') === 0) ? 'Windows' : PHP_OS_FAMILY;
        }

        Tools::outputInfo('cli', CLI::CLI_VERSION, 'bg-blue-600', 'w-6');
        Tools::outputInfo('sail', Sail::SAIL_VERSION, 'bg-blue-600', 'w-6');
        Tools::outputInfo('php', PHP_VERSION . ' (' . $os . ')', 'bg-purple-500', 'w-6');
        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this->setHelp("Get the current version of the CLI, SailCMS and the version PHP it's running on.");
    }
}