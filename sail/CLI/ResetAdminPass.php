<?php

namespace SailCMS\CLI;

use SailCMS\Errors\DatabaseException;
use SailCMS\Models\User;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class ResetAdminPass extends Command
{
    protected static $defaultDescription = 'Reset an administrator password (disabled in production)';
    protected static $defaultName = 'reset:password';

    /**
     *
     * @throws DatabaseException
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Do not allow this call in production
        if (env('ENVIRONMENT') === 'prod' || env('ENVIRONMENT') === 'production') {
            return Command::FAILURE;
        }

        $helper = $this->getHelper('question');
        $question = new Question('Email for account to reset: ', '');
        $email = $helper->ask($input, $output, $question);

        if ($email === '') {
            Tools::outputError("Email cannot be empty.");
            return Command::INVALID;
        }

        $user = new User();
        $found = $user->getByEmail($email);

        if ($found) {
            $question2 = new Question('New Password: ', '');
            $question2->setHidden(true);
            $question2->setHiddenFallback(false);

            $password = $helper->ask($input, $output, $question2);

            if ($password === '') {
                Tools::outputError("Password cannot be empty.");
                return Command::INVALID;
            }

            $result = User::changePasswordWithID($user->id, $password);

            if (!$result) {
                Tools::outputError("Password is not safe enough, make sure you follow your security rules found in `general.php`.");
                return Command::FAILURE;
            }

            return Command::SUCCESS;
        }

        Tools::outputError("Account does not exist for $email.");
        return Command::FAILURE;
    }

    protected function configure(): void
    {
        $this->setHelp("Reset an administrator password (disabled in production)");
    }
}