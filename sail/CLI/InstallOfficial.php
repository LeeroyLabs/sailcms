<?php

namespace SailCMS\CLI;

use SailCMS\Internal\Filesystem;
use SailCMS\Sail;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InstallOfficial extends Command
{
    protected static $defaultDescription = 'Install an official first-party package';
    protected static $defaultName = 'install:official';


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Make sure all that is required is set and available
        Tools::testFlight();
        $fs = Filesystem::manager();

        $name = $input->getArgument('name');

        if (str_contains($name, 'leeroy/sail')) {
            Tools::executeComposerInstall($name);

            // Read manifest file from package
            $packageJson = 'root://vendor/' . $name . '/sailcms.json';

            // Not installed? missing manifest?
            if (!$fs->fileExists($packageJson)) {
                Tools::outputError("Package {$name} did not install properly or does not have a manifest file.");
                return Command::FAILURE;
            }

            $manifest = json_decode($fs->read($packageJson), false, 10, JSON_THROW_ON_ERROR);

            // Manifest version management
            if ($manifest->manifestVersion === 1) {
                $minVer = $manifest->requiredMinimumVersion ?? Sail::SAIL_MAJOR_VERSION;
                $packageType = strtolower($manifest->packageType ?? 'unknown');
                $namespace = $manifest->namespace;
            } else {
                Tools::outputError("Package {$name} manifest is using an unsupported version.");
                return Command::FAILURE;
            }

            // Check if it's a supported type
            if ($packageType !== 'module' && $packageType !== 'container') {
                Tools::outputError("Package {$name} is not of supported type.");
                return Command::FAILURE;
            }

            // Check version requirement
            $isCompatible = Sail::verifyCompatibility($minVer);

            if ($isCompatible === -1) {
                Tools::outputError("Package {$name} has an invalid version compatibility operator. Please contact the package developer.");
                return Command::FAILURE;
            }

            if ($isCompatible === 0) {
                $installed = Sail::SAIL_VERSION;
                Tools::outputError("Package {$name} requires a version of SailCMS higher than what is installed (required: {$minVer}.0.0, installed: {$installed})");
                return Command::FAILURE;
            }

            $section = ($packageType === 'module') ? 'modules' : 'containers';

            // Update composer.json
            $json = json_decode($fs->read('root://composer.json'), false, 10, JSON_THROW_ON_ERROR);
            $json->sailcms->{$section} = [...$json->sailcms->{$section}, $namespace];
            $encoded = json_encode($json, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $fs->write('root://composer.json', $encoded);

            // Dump autoload and regenerate
            Tools::executeComposerRefresh();

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