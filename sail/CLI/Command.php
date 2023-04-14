<?php

namespace SailCMS\CLI;

use League\Flysystem\FilesystemException;
use SailCMS\Filesystem;
use SailCMS\Text;
use Symfony\Component\Console\Command\Command as CMD;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Command extends CMD
{
    protected static $defaultDescription = 'Create a new CLI command';
    protected static $defaultName = 'create:command';

    /**
     *
     * @throws FilesystemException
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        Tools::testFlight();
        $fs = Filesystem::manager();

        $type = strtolower($input->getArgument('type'));

        $location = Text::from($input->getArgument('location'))->deburr()->camel()->capitalize(true)->value();
        $name = Text::from($input->getArgument('name'))->deburr()->camel()->capitalize(true)->value();

        Tools::outputInfo('creating', "Creating command [b]{$name}[/b]");

        switch ($type) {
            case 'module':
            case 'modules':
                $path = "root://modules/{$location}";

                if ($fs->directoryExists($path)) {
                    if (!$fs->directoryExists($path . '/Commands')) {
                        $fs->createDirectory($path . '/Commands');
                    }

                    // Create file
                    $code = $fs->read("install://command.php");
                    $code = str_replace(['[NAME]', '[LOCATION]'], [$name, $location], $code);
                    $fs->write($path . '/Commands/' . $name . '.php', $code);

                    // Dump autoload and regenerate
                    Tools::executeComposerRefresh();

                    Tools::outputInfo("created", "Created command [b]{$name}[/b] in module [b]{$location}[/b]", 'bg-green-500');
                    return CMD::SUCCESS;
                }

                Tools::outputError("Cannot create command [b]{$name}[/b], module [b]{$location}[/b] does not exist", false, false);
                return CMD::FAILURE;

            case 'container':
            case 'containers':
                $path = "root://containers/{$location}";

                if ($fs->directoryExists($path)) {
                    if (!$fs->directoryExists($path . '/Commands')) {
                        $fs->createDirectory($path . '/Commands');
                    }

                    // Create file
                    $code = $fs->read("install://command.php");
                    $code = str_replace(['[NAME]', '[LOCATION]'], [$name, $location], $code);
                    $fs->write($path . '/Commands/' . $name . '.php', $code);

                    Tools::outputInfo("created", "Created command [b]{$name}[/b] in container [b]{$location}[/b]", 'bg-green-500');
                    return CMD::SUCCESS;
                }

                Tools::outputError("Cannot create command [b]{$name}[/b], container [b]{$location}[/b] does not exist");
                return CMD::FAILURE;

            default:
                Tools::outputError("Unknown location type. Valid options are [b]module[/b] and [b]container[/b]. Received [b]{$input->getArgument('type')}[/b]", false, false);
                return CMD::FAILURE;
        }
    }

    protected function configure(): void
    {
        $this->setHelp("Create a new custom CLI command.");
        $this->addArgument('type', InputArgument::REQUIRED, 'Set location type to container or module');
        $this->addArgument('location', InputArgument::REQUIRED, 'The container or module to install it in');
        $this->addArgument('name', InputArgument::REQUIRED, 'Name of the command');

        $this->addUsage("container containerName CommandName");
        $this->addUsage("module moduleName CommandName");
    }
}