<?php

namespace SailCMS\CLI;

use SailCMS\Queue as QueueMan;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Queue extends Command
{
    protected static $defaultDescription = 'Execute the Queue Processor';
    protected static $defaultName = 'run:queue';

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
        $this->setHelp("Execute the Queue Processor");
    }
}