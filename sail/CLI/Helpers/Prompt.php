<?php

namespace SailCMS\CLI\Helpers;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class Prompt
{
    public function __construct(private readonly Command $command, private InputInterface $input, private OutputInterface $output)
    {
    }

    /**
     *
     * Ask for information and return the input
     *
     * @param  string  $question
     * @return string
     *
     */
    public function request(string $question): string
    {
        $helper = $this->command->getHelper('question');
        $questionObj = new Question($question . ': ');
        return $helper->ask($this->input, $this->output, $questionObj);
    }

    // Confirmation

    // Choice

    // Autocomplete

    // Hidden Input (ex: Password)
}