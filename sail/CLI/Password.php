<?php

namespace SailCMS\CLI;

use League\Flysystem\FilesystemException;
use SailCMS\CLI;
use SailCMS\Filesystem;
use SailCMS\Sail;
use SailCMS\Security;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class Password extends Command
{
    protected static $defaultDescription = 'Encrypt a password using sail security tools';
    protected static $defaultName = 'create:password';

    /**
     *
     * Execute command
     *
     * @param  InputInterface   $input
     * @param  OutputInterface  $output
     * @return int
     * @throws FilesystemException
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        $question = new Question('Password: ', '');
        $question->setHidden(true);
        $question->setHiddenFallback(false);
        $pass = $helper->ask($input, $output, $question);

        if ($pass === '') {
            Tools::outputError("Password cannot be empty.");
            return Command::INVALID;
        }

        echo PHP_EOL . Security::hashPassword($pass) . PHP_EOL;
        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this->setHelp("Encrypt a password using sail security tools");
    }
}