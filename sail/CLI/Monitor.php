<?php

namespace SailCMS\CLI;

use League\Flysystem\FilesystemException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Sail;
use SailCMS\Security;
use SailCMS\SystemMonitor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class Monitor extends Command
{
    protected static $defaultDescription = 'Run the system monitor to take a sample of server health';
    protected static $defaultName = 'run:monitoring';

    /**
     *
     * Execute command
     *
     * @param  InputInterface   $input
     * @param  OutputInterface  $output
     * @return int
     * @throws \JsonException
     * @throws DatabaseException
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $samplephp = $input->getOption('php');
        $reportPHP = ($samplephp === 'yes');

        SystemMonitor::sample($reportPHP);
        Tools::outputInfo('Done', 'Health sample taken', 'bg-green-500');
        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this->addOption('php', null, InputOption::VALUE_OPTIONAL, 'Report on PHP version', 'no');
        $this->setHelp('Run the system monitor to take a sample of server health');
    }
}