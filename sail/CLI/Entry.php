<?php

namespace SailCMS\CLI;

use League\Flysystem\FilesystemException;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EntryException;
use SailCMS\Errors\PermissionException;
use SailCMS\Models\Entry as EntryModel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Entry extends Command
{
    protected static $defaultDescription = 'Indexation of entries';
    protected static $defaultName = 'entry:index';

    /**
     *
     * Execute indexation of entry for search
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws FilesystemException
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        Tools::testFlight();

        $entryTypeHandle = strtolower($input->getArgument('entry_type_handle'));
        Tools::outputInfo('indexing', "Indexing all [b]{$entryTypeHandle}[/b] entries");

        $count = EntryModel::indexByEntryType();
        Tools::outputInfo("indexed", "All [b]{$entryTypeHandle}[/b] entries have been indexed", 'bg-green-500');

        return $count;
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
        $this->setHelp("Index entry data.");
        $this->addArgument('entry_type_handle', InputArgument::REQUIRED, 'Index only the entry type (default = page)');
    }
}