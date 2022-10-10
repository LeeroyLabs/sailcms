<?php

namespace [LOCATION]\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class [NAME] extends Command
{
    // See https://symfony.com/doc/current/console.html#creating-a-command for help.

    protected static $defaultDescription = 'Description of your command';
    protected static $defaultName = 'commandName';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Return success or Command::FAILURE
        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this->setHelp("The help description here");
    }
}