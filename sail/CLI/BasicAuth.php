<?php

namespace SailCMS\CLI;

use League\Flysystem\FilesystemException;
use SailCMS\Sail;
use SailCMS\Security;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class BasicAuth extends Command
{
    protected static $defaultDescription = 'Create a new user/password for basic authentication';
    protected static $defaultName = 'create:auth';

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
        $question = new Question('Username: ', '');
        $user = $helper->ask($input, $output, $question);

        if ($user === '') {
            Tools::outputError('Username cannot be empty.');
            return Command::INVALID;
        }

        $question2 = new Question('Password: ', '');
        $question2->setHidden(true);
        $question2->setHiddenFallback(false);

        $password = $helper->ask($input, $output, $question2);

        if ($password === '') {
            Tools::outputError('Password cannot be empty.');
            return Command::INVALID;
        }

        $file = Sail::getFSDirectory() . '/vault/basic_auth';
        $encPass = Security::hashPassword($password);
        $format = $user . ":" . $encPass;

        file_put_contents($file, $format, FILE_APPEND);

        Tools::outputInfo('created', 'User/password pair created!', 'bg-green-500');
        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this->setHelp('Create a new user/password for basic authentication');
    }
}