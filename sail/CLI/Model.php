<?php

namespace SailCMS\CLI;

use League\Flysystem\FilesystemException;
use SailCMS\CLI;
use SailCMS\Filesystem;
use SailCMS\Sail;
use SailCMS\Text;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\Command as CMD;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Model extends Command
{
    protected static $defaultDescription = 'Create a new database model';
    protected static $defaultName = 'create:model';

    /**
     *
     * @param  InputInterface   $input
     * @param  OutputInterface  $output
     * @return int
     * @throws FilesystemException
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        Tools::testFlight();
        $fs = Filesystem::manager();

        $type = strtolower($input->getArgument('type'));

        $location = ucfirst(Text::camelCase(Text::deburr($input->getArgument('location'))));
        $name = ucfirst(Text::camelCase(Text::deburr($input->getArgument('name'))));

        Tools::outputInfo('creating', "Creating model [b]{$name}[/b]");

        switch ($type) {
            case 'module':
            case 'modules':
                $path = "root://modules/{$location}";

                if ($fs->directoryExists($path)) {
                    if (!$fs->directoryExists($path . '/Models')) {
                        $fs->createDirectory($path . '/Models');
                    }

                    // Create file
                    $code = $fs->read("install://model.php");
                    $code = str_replace(['[NAME]', '[LOCATION]'], [$name, $location], $code);
                    $fs->write($path . '/Models/' . $name . '.php', $code);

                    Tools::outputInfo("created", "Created model [b]{$name}[/b] in module [b]{$location}[/b]", 'bg-green-500');
                    return CMD::SUCCESS;
                }

                Tools::outputError("Cannot create model [b]{$name}[/b], module [b]{$location}[/b] does not exist", false, false);
                return CMD::FAILURE;

            case 'container':
            case 'containers':
                $path = "root://containers/{$location}";

                if ($fs->directoryExists($path)) {
                    if (!$fs->directoryExists($path . '/Models')) {
                        $fs->createDirectory($path . '/Models');
                    }

                    // Create file
                    $code = $fs->read("install://model.php");
                    $code = str_replace(['[NAME]', '[LOCATION]'], [$name, $location], $code);
                    $fs->write($path . '/Models/' . $name . '.php', $code);

                    Tools::outputInfo("created", "Created model [b]{$name}[/b] in container [b]{$location}[/b]", 'bg-green-500');
                    return CMD::SUCCESS;
                }

                Tools::outputError("Cannot create model [b]{$name}[/b], container [b]{$location}[/b] does not exist");
                return CMD::FAILURE;

            default:
                Tools::outputError("Unknown location type. Valid options are [b]module[/b] and [b]container[/b]. Received [b]{$input->getArgument('type')}[/b]", false, false);
                return CMD::FAILURE;
        }
    }

    protected function configure(): void
    {
        $this->setHelp("Create a new database model.");
        $this->addArgument('type', InputArgument::REQUIRED, 'Set location type to container or module');
        $this->addArgument('location', InputArgument::REQUIRED, 'The container or module to install it in');
        $this->addArgument('name', InputArgument::REQUIRED, 'The name of your model');
    }
}