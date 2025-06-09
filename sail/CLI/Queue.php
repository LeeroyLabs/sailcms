<?php

namespace SailCMS\CLI;

use SailCMS\Queue as QueueMan;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Queue extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $queue = QueueMan::manager();

        try {
            $queue->process();
            return Command::SUCCESS;
        } catch (\Exception $e) {
            return Command::FAILURE;
        }
    }

    protected function configure(): void
    {
        $this->setName('run:queue');
        $this->setDescription('Execute the Queue Processor');
        $this->setHelp("Execute the Queue Processor");
    }
}