<?php

namespace SailCMS\Templating;

use SailCMS\Debug;
use SailCMS\Sail;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Extension\AbstractExtension;
use Twig\Lexer;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use SailCMS\Templating\Extensions\Bundled;
use SailCMS\Templating\Extensions\Navigation;
use Twig\TwigFunction;

class Engine
{
    private Environment $twig;
    private static array $filters = [];
    private static array $functions = [];
    private static array $extensions = [];
    private static FilesystemLoader $loader;

    public function __construct()
    {
        if (!isset(self::$loader)) {
            self::$loader = new FilesystemLoader([Sail::getTemplateDirectory(), dirname(__DIR__, 2) . '/cms']);
        }

        // Use template caching or not
        if (setting('templating.cache', false)) {
            $this->twig = new Environment(self::$loader, [
                'cache' => Sail::getCacheDirectory(),
            ]);
        } else {
            $this->twig = new Environment(self::$loader);
        }

        // Set tags to enable twig and vue side by side
        if (setting('templating.vueCompat', false)) {
            $lexer = new Lexer($this->twig, [
                'tag_comment' => ['[*', '*]'],
                'tag_block' => ['[%', '%]'],
                'tag_variable' => ['[=', '=]'],
                'interpolation' => ['[[', ']]'],
            ]);

            $this->twig->setLexer($lexer);
        }

        $this->setupExtensions();
    }

    /**
     *
     * Add a template path for Twig
     *
     * @param  string  $path
     * @return void
     * @throws LoaderError
     *
     */
    public static function addTemplatePath(string $path): void
    {
        if (!isset(self::$loader)) {
            self::$loader = new FilesystemLoader([Sail::getTemplateDirectory(), dirname(__DIR__, 2) . '/cms']);
        }

        self::$loader->addPath($path);
    }

    /**
     *
     * Render an HTML template using Twig
     *
     * @param  string  $file
     * @param  object  $data
     * @return string
     * @throws LoaderError|RuntimeError|SyntaxError
     *
     */
    public function render(string $file, object $data): string
    {
        $st = microtime(true);

        // Add some last minutes variables to the template
        $data->paths = (object)[
            'images' => '/public/images',
            'css' => '/public/css',
            'js' => '/public/js',
            'fonts' => '/public/fonts',
            'public' => '/public'
        ];

        ob_start();
        $this->twig->display($file . '.twig', (array)$data);
        $html = ob_get_clean();
        Debug::view($file, (array)$data, $st);
        return $html;
    }

    /**
     *
     * Register a filter for use in templates
     *
     * @param  string    $name
     * @param  callable  $callback
     * @return void
     *
     */
    public static function addFilter(string $name, callable $callback): void
    {
        self::$filters[] = new TwigFilter($name, $callback);
    }

    /**
     *
     * Register a function for use in templates
     *
     * @param  string    $name
     * @param  callable  $callback
     * @return void
     *
     */
    public static function addFunction(string $name, callable $callback): void
    {
        self::$functions[] = new TwigFunction($name, $callback);
    }

    /**
     *
     * Add an extension for use in templates
     *
     * @param  AbstractExtension  $extension
     * @return void
     *
     */
    public static function addExtension(AbstractExtension $extension): void
    {
        self::$extensions[] = $extension;
    }

    /**
     *
     * Get registered extensions, filters and functions
     *
     * @return array
     *
     */
    public static function getExtensions(): array
    {
        return [
            'extensions' => self::$extensions,
            'filters' => self::$filters,
            'functions' => self::$functions
        ];
    }

    // -------------------------------------------------- Private -------------------------------------------------- //

    private function setupExtensions(): void
    {
        $this->twig->addExtension(new Bundled());
        $this->twig->addExtension(new Navigation());

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