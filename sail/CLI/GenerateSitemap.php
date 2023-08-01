<?php

namespace SailCMS\CLI;

use Exception;
use League\Flysystem\FilesystemException;
use RuntimeException;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\PermissionException;
use SailCMS\GlobalSeo;
use SailCMS\Models\EntryType;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateSitemap extends Command
{
    protected static $defaultDescription = 'Generate sitemaps';
    protected static $defaultName = 'seo:sitemap';

    /**
     *
     * Execute indexation of entry for search
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws FilesystemException
     * @throws Exception
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        Tools::testFlight();

        Tools::outputInfo('generating', "Generating sitemap.xml");

        try {
            (new GlobalSeo())->generateSitemap();
        } catch (ACLException|DatabaseException|PermissionException $e) {
            throw new RuntimeException($e->getMessage());
        }

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
        $this->setHelp("Index entry data.");
    }
}