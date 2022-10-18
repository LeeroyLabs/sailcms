<?php

namespace SailCMS\CLI;

use League\Flysystem\FilesystemException;
use SailCMS\CLI;
use SailCMS\Sail;
use SailCMS\Security;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Install extends Command
{
    protected static $defaultDescription = 'Install SailCMS';
    protected static $defaultName = 'run:install';

    /**
     *
     * @throws FilesystemException
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        Tools::testFlight(true);

        // Folders to create
        $folders = [
            'templates',
            'templates/default',
            'web',
            'web/public',
            'web/public/default',
            'web/public/default/images',
            'web/public/default/css',
            'web/public/default/js',
            'web/public/default/fonts',
            'storage',
            'storage/fs',
            'storage/fs/default',
            'storage/fs/default/uploads',
            'storage/cache/',
            'storage/cache/default',
            'storage/vault',
            'storage/vault/default',
            'config',
            'config/default',
            'modules',
            'containers',
            'locales',
            'locales/default'
        ];

        // Files to create (dest => src)
        $files = [
            'web/index.php' => 'index.php',
            'web/.htaccess' => 'htaccess',
            'web/public/default/images/.gitkeep' => '',
            'web/public/default/css/.gitkeep' => '',
            'web/public/default/js/.gitkeep' => '',
            'web/public/default/fonts/.gitkeep' => '',
            'config/default/general.php' => 'config/general.php',
            'config/apps.env.php' => 'env.php',
            'config/security.php' => 'security.php',
            'modules/.gitkeep' => '',
            'templates/default/.gitkeep' => '',
            '.env.default' => 'env.default',
            'storage/fs/default/uploads/.gitkeep' => '',
            'storage/cache/default/.gitkeep' => '',
            'locales/default/en.yaml' => ''
        ];

        Tools::showTitle('Installing SailCMS v' . Sail::SAIL_VERSION);

        foreach ($folders as $folder) {
            $concurrentDirectory = CLI::getWorkingDirectory() . '/' . $folder;

            if (!file_exists($concurrentDirectory)) {
                if (!mkdir($concurrentDirectory) && !is_dir($concurrentDirectory)) {
                    throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
                }
            }
        }

        $workPath = dirname(__DIR__, 2) . '/install/';

        foreach ($files as $key => $file) {
            if (str_contains($key, '.gitkeep')) {
                touch(CLI::getWorkingDirectory() . '/' . $key);
            } else {
                if ($file === '') {
                    continue;
                }

                file_put_contents(CLI::getWorkingDirectory() . '/' . $key, file_get_contents($workPath . $file));
            }
        }

        // Generate Security Key file
        Tools::outputInfo('generate', 'Generating secure key for encryption', 'bg-sky-400');
        Security::init();

        // Generate Admin user
        Tools::outputInfo('create', 'Generating Admin user', 'bg-sky-400');
        Tools::outputInfo('created', "User is 'admin' and the password is 'entergeneratedpasswordhere'", 'bg-green-500');

        // TODO : FINISH THIS

        Tools::outputInfo('optimizing', 'Making sure everything is optimized in the database', 'bg-sky-400');
        // TODO : FINISH THIS

        Tools::outputInfo('success', 'Installation complete. You are ready to go! 🚀', 'bg-green-500');
        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this->setHelp("Install everything needed to get started with SailCMS");
    }
}