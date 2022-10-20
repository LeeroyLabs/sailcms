<?php

namespace SailCMS\Templating;

use League\Flysystem\FilesystemException;
use SailCMS\Errors\FileException;
use SailCMS\Filesystem;
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
use Twig\TwigFunction;

class Engine
{
    private Environment $twig;
    private static array $filters = [];
    private static array $functions = [];
    private static array $extensions = [];

    public function __construct()
    {
        $loader = new FilesystemLoader([Sail::getTemplateDirectory(), dirname(__DIR__, 2) . '/cms']);

        // Use template caching or not
        if ($_ENV['SETTINGS']->get('templating.cache')) {
            $this->twig = new Environment($loader, [
                'cache' => Sail::getCacheDirectory(),
            ]);
        } else {
            $this->twig = new Environment($loader);
        }

        // Set tags to enable twig and vue side by side
        if ($_ENV['SETTINGS']->get('templating.vueCompat')) {
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
     * @param  string  $file
     * @param  object  $data
     * @return string
     * @throws FileException|FilesystemException|LoaderError|RuntimeError|SyntaxError
     *
     */
    public function render(string $file, object $data): string
    {
        $fs = Filesystem::manager();
        $target = 'root://templates/' . $file . '.twig';
        $target2 = 'cms://' . $file . '.twig';
        $html = '';

        // Add some last minutes variables to the template
        $data->paths = (object)[
            'images' => '/public/images',
            'css' => '/public/css',
            'js' => '/public/js',
            'fonts' => '/public/fonts',
            'public' => '/public'
        ];

        if ($fs->fileExists($target) || $fs->fileExists($target2)) {
            ob_start();
            $this->twig->display($file . '.twig', (array)$data);
            $html = ob_get_clean();
        } else {
            throw new FileException("Template {$file} does not exist, please make sure it exists before using it", 0404);
        }

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
        static::$filters[] = new TwigFilter($name, $callback);
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
        static::$filters[] = new TwigFunction($name, $callback);
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
        static::$extensions[] = $extension;
    }

    // -------------------------------------------------- Private -------------------------------------------------- //

    private function setupExtensions(): void
    {
        $this->twig->addExtension(new Bundled());

        foreach (static::$extensions as $extension) {
            $this->twig->addExtension($extension);
        }

        foreach (static::$filters as $filter) {
            $this->twig->addFilter($filter);
        }

        foreach (static::$functions as $function) {
            $this->twig->addFunction($function);
        }
    }
}