<?php

namespace SailCMS\CLI\Helpers;

use SailCMS\Collection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
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

    /**
     *
     * Confirm yes/no to the question
     *
     * @param  string  $question
     * @param  string  $default
     * @return string
     *
     */
    public function confirm(string $question, string $default = '0'): string
    {
        $helper = $this->command->getHelper('question');
        $questionObj = new ChoiceQuestion($question, ['yes', 'no'], $default);
        $questionObj->setMultiselect(false);
        return $helper->ask($this->input, $this->output, $questionObj);
    }

    /**
     *
     * Select one or many from a list
     *
     * @param  string      $question
     * @param  Collection  $choices
     * @param  bool        $multi
     * @param  string      $default
     * @return string
     *
     */
    public function choice(string $question, Collection $choices, bool $multi = false, string $default = '0'): string
    {
        $helper = $this->command->getHelper('question');
        $questionObj = new ChoiceQuestion($question, $choices->unwrap(), $default);
        $questionObj->setMultiselect($multi);
        return $helper->ask($this->input, $this->output, $questionObj);
    }

    /**
     *
     * Choice with autocomplete
     *
     * @param  string      $question
     * @param  Collection  $choices
     * @param  string      $default
     * @return string
     *
     */
    public function choiceAutocomplete(string $question, Collection $choices, string $default = '0'): string
    {
        $helper = $this->command->getHelper('question');
        $questionObj = new Question($question . ': ', $default);
        $questionObj->setAutocompleterValues($choices);
        return $helper->ask($this->input, $this->output, $questionObj);
    }

    /**
     *
     * Ask for hidden input (best suited for passwords)
     *
     * @param  string  $text
     * @return mixed
     *
     */
    public function hiddenInput(string $text)
    {
        $helper = $this->command->getHelper('question');
        $questionObj = new Question($text);
        $questionObj->setHidden(true);
        $questionObj->setHiddenFallback(false);
        return $helper->ask($this->input, $this->output, $questionObj);
    }

    // Hidden Input (ex: Password)
}