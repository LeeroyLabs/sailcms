<?php

namespace SailCMS\CLI;

use League\Flysystem\FilesystemException;
use RuntimeException;
use SailCMS\CLI;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\PermissionException;
use SailCMS\Models\Role;
use SailCMS\Models\User;
use SailCMS\Sail;
use SailCMS\Security;
use SailCMS\Types\Username;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class Install extends Command
{
    protected static $defaultDescription = 'Install SailCMS';
    protected static $defaultName = 'run:install';

    /**
     * @param  InputInterface   $input
     * @param  OutputInterface  $output
     * @return int
     * @throws FilesystemException
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
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
            'serverless_crons'
        ];

        // Files to create (dest => src)
        $files = [
            'web/index.php' => 'index.php',
            'web/.htaccess' => 'htaccess',
            'web/public/images/.gitkeep' => '',
            'web/public/css/.gitkeep' => '',
            'web/public/js/.gitkeep' => '',
            'web/public/fonts/.gitkeep' => '',
            'config/general.dev.php' => 'config/general.dev.php',
            'config/general.staging.php' => 'config/general.staging.php',
            'config/general.production.php' => 'config/general.production.php',
            'config/security.php' => 'security.php',
            'config/boot.php' => 'boot.php',
            'modules/.gitkeep' => '',
            'templates/default/.gitkeep' => '',
            '.env' => 'env',
            'storage/fs/uploads/.gitkeep' => '',
            'storage/fs/logs/.gitkeep' => '',
            'storage/cache/.gitkeep' => '',
            'storage/debug/.gitkeep' => '',
            'storage/fs/vault/basic_auth' => '',
            'locales/en.yaml' => '',
            'templates/default/email/new_account.twig' => 'account.email.twig',
            'serverless_crons/.gitkeep' => ''
        ];

        Tools::showTitle('Installing SailCMS v' . Sail::SAIL_VERSION);

        foreach ($folders as $folder) {
            $concurrentDirectory = CLI::getWorkingDirectory() . '/' . $folder;

            if (!file_exists($concurrentDirectory)) {
                if (!mkdir($concurrentDirectory) && !is_dir($concurrentDirectory)) {
                    throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
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

        // Ask user email
        $helper = $this->getHelper('question');
        $question = new Question('Email for your user: ', '');
        $email = $helper->ask($input, $output, $question);

        if ($email === '') {
            $email = 'no@email.com';
        }

        // Generate password
        $password = substr(Security::hashPassword(Security::secureTemporaryKey()), 0, 16);

        // Create user
        $userModel = new User();
        $userModel->create(
            new Username('Administrator', ''),
            $email,
            $password,
            ['super-administrator']
        );

        // Create Super Admin and Admin roles
        $roleModel = new Role();
        try {
            $roleModel->create('Super Administrator', 'Can administrate the entire system', ['*'], 1000);
            $roleModel->create('Administrator', 'Can administrate the almost the entire system', ['*'], 950);
        } catch (RuntimeException $e) {
            // Already created
        }

        // Done for user
        Tools::outputInfo('created', "User {$email} and the password is [b]{$password}[/b]", 'bg-green-500');

        Tools::outputInfo('optimizing', 'Making sure everything is optimized in the database', 'bg-sky-400');
        Sail::ensurePerformance();

        // TODO: CREATE BASIC EMAIL TEMPLATES (ex: Account, Forgot Password)

        Tools::outputInfo('success', 'Installation complete. You are ready to go! 🚀', 'bg-green-500');
        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this->setHelp("Install everything needed to get started with SailCMS");
    }
}