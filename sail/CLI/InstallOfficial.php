<?php

namespace SailCMS\CLI;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InstallOfficial extends Command
{
    protected static $defaultDescription = 'Install an official first-party package';
    protected static $defaultName = 'install:official';

    /**
     *
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');

        if (str_contains($name, 'leeroy/sail')) {
            Tools::executeComposerInstall($name);
            Tools::outputInfo('created', "Package {$name} has been installed and is ready to go! 🚀", 'bg-green-500');
            return Command::SUCCESS;
        }

        Tools::outputError("Package {$name} is not an official package.");
        return Command::FAILURE;
    }

    protected function configure(): void
    {
        $this->setHelp('This installs official packages to your project.');
        $this->addArgument('name', InputArgument::REQUIRED, 'Package name');
    }
}