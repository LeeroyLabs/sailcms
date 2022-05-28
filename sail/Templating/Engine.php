<?php

namespace SailCMS\Templating;

use SailCMS\Sail;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Lexer;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

class Engine
{
    private Environment $twig;
    private static array $filters = [];
    private static array $functions = [];
    private static array $extensions = [];

    public function __construct()
    {
        $loader = new FilesystemLoader(Sail::getTemplateDirectory());

        // Use template caching or not
        if ($_ENV['settings']->get('templating.cache')) {
            $this->twig = new Environment($loader, [
                'cache' => Sail::getCacheDirectory(),
            ]);
        } else {
            $this->twig = new Environment($loader);
        }

        // Set tags to enable twig and vue side by side
        if ($_ENV['settings']->get('templating.vueCompat')) {
            $lexer = new Lexer($this->twig, [
                'tag_comment' => $settings['syntax']['comment'] ?? ['[*', '*]'],
                'tag_block' => $settings['syntax']['block'] ?? ['[%', '%]'],
                'tag_variable' => $settings['syntax']['variable'] ?? ['[=', '=]'],
                'interpolation' => $settings['syntax']['interpolation'] ?? ['[[', ']]'],
            ]);

            $this->twig->setLexer($lexer);
        }

        $this->setupExtensions();
    }

    /**
     *
     * Render an HTML template using Twig
     *
     * @param string $file
     * @param object $data
     * @return string
     *
     */
    public function render(string $file, object $data): string
    {
        return '';
    }

    /**
     *
     * Register a filter for use in templates
     *
     * @param string $name
     * @param callable $callback
     * @return void
     *
     */
    public static function addFilter(string $name, callable $callback): void
    {
        static::$filters[] = new TwigFilter($name, $callback);
    }

    /**
     *
     * Register a function for use in templates
     *
     * @param string $name
     * @param callable $callback
     * @return void
     *
     */
    public static function addFunction(string $name, callable $callback): void
    {
        static::$filters[] = ['name' => $name, 'callback' => $callback];
    }

    /**
     *
     * Add an extension for use in templates
     *
     * @param AbstractExtension $extension
     * @return void
     *
     */
    public static function addExtension(AbstractExtension $extension): void
    {
        static::$extensions[] = $extension;
    }

    // -------------------------------------------------- Private  -------------------------------------------------- //

    private function setupExtensions(): void
    {
        $this->twig->addFunction($debug);
        $this->twig->addFunction($env);

        foreach (self::$extensions as $extension) {
            $this->twig->addExtension($extension);
        }

        foreach (self::$filters as $filter) {
            $this->twig->addFilter($filter);
        }

        foreach (self::$functions as $function) {
            $this->twig->addFunction($function);
        }
    }
}