<?php

namespace SailCMS\CLI;

use Exception;
use SailCMS\Models\Entry as EntryModel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class View extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        Tools::testFlight();

        Tools::outputInfo('generating', "Generating all views for entries");

        try {
            EntryModel::generateAllViews();
        } catch (Exception $exception) {
            Tools::outputError("There was an error when executing the command: {$exception->getMessage()}.");
            return Command::FAILURE;
        }

        Tools::outputInfo("generated", "All views has been generated for all entries", 'bg-green-500');

        return Command::SUCCESS;
    }

    /**
     *
     * Configuration of the command
     *
     * @return void
     *
     */
    protected function configure(): void
    {
        $this->setName('generate:views');
        $this->setDescription('Create views for entries');
        $this->setHelp("Create views for all entry types.");
    }
}