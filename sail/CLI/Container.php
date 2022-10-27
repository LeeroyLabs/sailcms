<?php

namespace SailCMS\CLI;

use JsonException;
use League\Flysystem\FilesystemException;
use SailCMS\Filesystem;
use SailCMS\Text;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Container extends Command
{
    protected static $defaultDescription = 'Creates a new container scaffolding';
    protected static $defaultName = 'create:container';

    /**
     *
     * @throws FilesystemException
     * @throws JsonException
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Make sure all that is required is set and available
        Tools::testFlight();
        $fs = Filesystem::manager();

        Tools::outputInfo('create', "Creating Container [b]{$input->getArgument('name')}[/b]");

        $name = ucfirst(Text::camelCase(Text::deburr($input->getArgument('name'))));
        $path = "root://containers/{$name}";

        if (!$fs->directoryExists($path)) {
            // Create module directory
            $fs->createDirectory($path);
            $fs->createDirectory($path . '/Commands');
            $fs->createDirectory($path . '/Controllers');
            $fs->createDirectory($path . '/Graphql');

            // Create all 3 files
            $fs->write($path . '/Graphql/queries.graphql', '');
            $fs->write($path . '/Graphql/mutations.graphql', '');
            $fs->write($path . '/Graphql/types.graphql', '');

            // Create module file
            $mod = $fs->read('install://container.php');
            $mod = str_replace('[NAME]', $name, $mod);
            $fs->write($path . '/Container.php', $mod);

            // Create middleware file
            $mod = $fs->read('install://middleware.php');
            $mod = str_replace('[NAME]', $name, $mod);
            $fs->write($path . '/Middleware.php', $mod);

            Tools::outputInfo('created', "Created [b]{$name}[/b] container Successfully!", 'bg-green-500');
            Tools::outputInfo('install', "Adding [b]{$name}[/b] to autoload...");

            // Install the configuration required in composer.json
            $json = json_decode($fs->read('root://composer.json'), false, 10, JSON_THROW_ON_ERROR);
            $json->sailcms->containers = [...$json->sailcms->containers, $name];
            $json->autoload->{'psr-4'}->{$name . '\\'} = 'containers/' . $name;

            $encoded = json_encode($json, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $fs->write('root://composer.json', $encoded);

            // Dump autoload and regenerate
            Tools::executeComposerRefresh();

            Tools::outputInfo('installed', "Container [b]{$name}[/b] is installed and ready to go! 🚀", 'bg-green-500');
            return Command::SUCCESS;
        }

        Tools::outputError("Container directory already exists, please make sure it's not a mistake.");
        return Command::FAILURE;
    }

    protected function configure(): void
    {
        $this->setHelp('This command generates the code for a new container scaffold and installs it for you.');
        $this->addArgument('name', InputArgument::REQUIRED, 'The name of your container');
    }
}