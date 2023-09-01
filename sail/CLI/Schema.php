<?php

namespace SailCMS\CLI;

use GraphQL\Error\SyntaxError;
use GraphQL\Language\Parser;
use GraphQL\Utils\AST;
use League\Flysystem\FilesystemException;
use SailCMS\GraphQL;
use SailCMS\Internal\Filesystem;
use SailCMS\Locale;
use SailCMS\Types\UserMeta;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Schema extends Command
{
    protected static $defaultDescription = 'Build an optimized AST version of the GraphQL Schema for production';
    protected static $defaultName = 'build:schema';

    /**
     *
     * @param  InputInterface   $input
     * @param  OutputInterface  $output
     * @return int
     * @throws SyntaxError
     * @throws FilesystemException
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $hidecms = $input->getOption('hidecms');
        Tools::outputInfo('compile', "Compiling AST schema for [b]GraphQL[/b]");

        $pathAST = 'cache://graphql.ast';

        // Load all files for the schema
        $queries = [];
        $mutations = [];
        $types = [];

        foreach (GraphQL::$querySchemaParts as $file) {
            $queries[] = file_get_contents($file);
        }

        foreach (GraphQL::$mutationSchemaParts as $file) {
            $mutations[] = file_get_contents($file);
        }

        foreach (GraphQL::$typeSchemaParts as $file) {
            $types[] = file_get_contents($file);
        }

        $locales = Locale::getAvailableLocales();
        $localeString = '';

        foreach ($locales as $locale) {
            $localeString .= "{$locale}: String\n";
        }

        if ($hidecms === null || $hidecms === 'no') {
            $schemaContent = file_get_contents(dirname(__DIR__) . '/GraphQL/schema.graphql');
            $schemaContent = str_replace(
                [
                    '#{CUSTOM_QUERIES}#',
                    '#{CUSTOM_MUTATIONS}#',
                    '#{CUSTOM_TYPES}#',
                    '#{CUSTOM_FLAGS}#',
                    '#{CUSTOM_META}#',
                    '#{CUSTOM_META_INPUT}#',
                    '#{LOCALE_FIELDS}#'
                ],
                [
                    implode("\n", $queries),
                    implode("\n", $mutations),
                    implode("\n", $types),
                    UserMeta::getAvailableFlags(),
                    UserMeta::getAvailableMeta(),
                    UserMeta::getAvailableMeta(true),
                    $localeString
                ],
                $schemaContent
            );
        } else {
            Tools::outputInfo('OPTION', "Compiling without SailCMS");
            $schemaContent = file_get_contents(dirname(__DIR__) . '/GraphQL/empty_schema.graphql');
            $schemaContent = str_replace(
                [
                    '#{CUSTOM_QUERIES}#',
                    '#{CUSTOM_MUTATIONS}#',
                    '#{CUSTOM_TYPES}#'
                ],
                [
                    implode("\n", $queries),
                    implode("\n", $mutations),
                    implode("\n", $types),
                ],
                $schemaContent
            );
        }

        // Parse schema
        echo $schemaContent;
        $document = Parser::parse($schemaContent);

        // Save AST format
        Filesystem::manager()->write($pathAST, "<?php\nreturn " . var_export(AST::toArray($document), true) . ";\n");

        Tools::outputInfo('success', "AST Schema was compiled successfully!", 'bg-green-500');
        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this->addOption('hidecms', null, InputOption::VALUE_OPTIONAL, 'HideCMS in AST', 'no');
        $this->setHelp("Build an optimized AST version of the GraphQL Schema for production");
    }
}