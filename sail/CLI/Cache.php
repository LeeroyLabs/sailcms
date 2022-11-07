<?php

namespace SailCMS\CLI;

use League\Flysystem\FilesystemException;
use SailCMS\CLI;
use SailCMS\Filesystem;
use SailCMS\Sail;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Cache extends Command
{
    protected static $defaultDescription = 'Clear SailCMS caches (twig, graphql, custom)';
    protected static $defaultName = 'clear-cache';

    /**
     *
     * Execute command
     *
     * @param  InputInterface   $input
     * @param  OutputInterface  $output
     * @return int
     * @throws FilesystemException
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        Tools::outputInfo('IO', "Clearing caches...");

        $fs = Filesystem::manager();
        $files = $fs->listContents('cache://', true);
        $debugfiles = $fs->listContents('debug://', true);

        foreach ($files as $file) {
            if (!str_contains($file->path(), '://.')) {
                $fs->delete($file->path());
            }
        }

        foreach ($debugfiles as $file) {
            if (!str_contains($file->path(), '://.')) {
                $fs->delete($file->path());
            }
        }

        Tools::outputInfo('SUCCESS', "Cleaned! ✨", 'bg-green-500');

        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this->setHelp("Clear the SailCMS caches (twig, graphql, custom)");
    }
}