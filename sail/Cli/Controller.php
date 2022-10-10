<?php

namespace SailCMS\Cli;

use League\Flysystem\FilesystemException;
use SailCMS\Filesystem;
use SailCMS\Text;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Controller extends Command
{
    protected static $defaultDescription = 'Creates a new controller in the requested container directory';
    protected static $defaultName = 'create:controller';

    /**
     *
     * @throws FilesystemException
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Make sure all that is required is set and available
        Tools::testFlight();
        $fs = Filesystem::manager();

        // Only catch the first 2, rest is ignored
        $container = $input->getArgument('container');
        $name = $input->getArgument('name');

        $container = ucfirst(Text::camelCase(Text::deburr($container)));
        $path = "root://containers/{$container}";

        $name = ucfirst(Text::camelCase(Text::deburr($name)));

        if ($fs->directoryExists($path)) {
            Tools::outputInfo('create', "Creating Controller [b]{$input->getArgument('name')}[/b]");

            if (!$fs->directoryExists($path . '/Controllers')) {
                // Create controller directory
                $fs->createDirectory($path . '/Controllers');
            }

            $ctrl = $fs->read('install://controller.php');
            $ctrl = str_replace(['[CONTAINER]', '[NAME]'], [$container, $name], $ctrl);

            $fs->write($path . '/Controllers/' . $name . '.php', $ctrl);
            Tools::outputInfo('created', "Controller {$name} has been created and is ready to go! 🚀", 'bg-green-500');
            return Command::SUCCESS;
        }

        Tools::outputError("Controller already exists, please make sure it's not a mistake.");
        return Command::FAILURE;
    }

    protected function configure(): void
    {
        $this->setHelp("Creates a new controller in the requested container directory.");
        $this->addArgument('container', InputArgument::REQUIRED, 'The name of your container');
        $this->addArgument('name', InputArgument::REQUIRED, 'The name of your controller');
    }
}