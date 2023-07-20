<?php

namespace SailCMS\CLI;

use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\PermissionException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TestTaskCommand extends Command
{
    protected static $defaultDescription = 'Test command for tasks';
    protected static $defaultName = 'test:task-command';

    /**
     *
     * Test function
     *
     * @param  InputInterface   $input
     * @param  OutputInterface  $output
     * @return int
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        sleep(30);

        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this->setHelp("Test command for tasks");
    }
}