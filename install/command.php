<?php

namespace [LOCATION]\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class [NAME] extends Command
{
    // See https://symfony.com/doc/current/console.html#creating-a-command for help.
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Return success or Command::FAILURE
        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this->setName('command:name');
        $this->setDescription('Description of your command');
        $this->setHelp("The help description here");
    }
}