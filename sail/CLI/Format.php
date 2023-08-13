<?php

namespace SailCMS\CLI;

use League\Flysystem\FilesystemException;
use SailCMS\Internal\Filesystem;
use SailCMS\Sail;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Format extends Command
{
    protected static $defaultDescription = 'Format your code with your requested settings';
    protected static $defaultName = 'format';

    /**
     * Execute command
     *
     * @param  InputInterface   $input
     * @param  OutputInterface  $output
     * @return int
     *
     * @throws FilesystemException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        Tools::outputInfo('LINT', 'Formatting your code...');

        // Load user configurations
        $configs = include Sail::getWorkingDirectory() . '/config/format.php';

        // Write temp file for it
        $tempFile = Sail::getWorkingDirectory() . '/storage/cache/format.php';
        $conf = file_get_contents(dirname(__DIR__, 2) . '/cms/format.base.php');
        $conf = str_replace(['#DIR#', '#RULES#'], [
            var_export($configs['include'], true),
            var_export($configs['rules'], true),
        ], $conf);

        Filesystem::manager()->write('cache://format.php', $conf);

        exec('php vendor/bin/php-cs-fixer fix --allow-risky=yes --show-progress=dots --config=' . $tempFile, $out);
        Tools::outputInfo('SUCCESS', 'Your code has been formatted! ✨', 'bg-green-500');

        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this->setHelp('Format your code with your requested settings');
    }
}
