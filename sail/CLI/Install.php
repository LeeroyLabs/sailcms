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
            'templates/default/email',
            'web',
            'web/public',
            'web/public/images',
            'web/public/css',
            'web/public/js',
            'web/public/fonts',
            'storage',
            'storage/debug',
            'storage/fs',
            'storage/fs/uploads',
            'storage/fs/logs',
            'storage/fs/vault',
            'storage/cache/',
            'config',
            'modules',
            'containers',
            'locales',
        ];

        // Files to create (dest => src)
        $files = [
            'web/index.php' => 'index.php',
            'web/.htaccess' => 'htaccess',
            'web/public/images/.gitkeep' => '',
            'web/public/css/.gitkeep' => '',
            'web/public/js/.gitkeep' => '',
            'web/public/fonts/.gitkeep' => '',
            'config/general.php' => 'config/general.php',
            'config/security.php' => 'security.php',
            'modules/.gitkeep' => '',
            'templates/default/.gitkeep' => '',
            '.env' => 'env',
            'storage/fs/uploads/.gitkeep' => '',
            'storage/fs/logs/.gitkeep' => '',
            'storage/cache/.gitkeep' => '',
            'storage/debug/.gitkeep' => '',
            'locales/en.yaml' => '',
            'templates/default/email/account.twig' => 'account.email.twig'
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

        // Create a symlink for 'assets' to the upload folder
        symlink(CLI::getWorkingDirectory() . '/storage/fs/uploads', 'web/assets');

        // Generate Security Key file
        Tools::outputInfo('generate', 'Generating secure key for encryption', 'bg-sky-400');
        Security::init();

        // Generate Admin user
        Tools::outputInfo('create', 'Generating Admin user', 'bg-sky-400');
        Tools::outputInfo('created', "User is 'admin' and the password is 'entergeneratedpasswordhere'", 'bg-green-500');

        // TODO : FINISH THIS

        Tools::outputInfo('optimizing', 'Making sure everything is optimized in the database', 'bg-sky-400');
        // TODO : FINISH THIS

        // TODO: CREATE BASIC EMAIL TEMPLATES (ex: Account, Forgot Password)

        Tools::outputInfo('success', 'Installation complete. You are ready to go! 🚀', 'bg-green-500');
        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this->setHelp("Install everything needed to get started with SailCMS");
    }
}