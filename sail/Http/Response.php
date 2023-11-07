<?php

namespace SailCMS\Http;

use JsonException;
use League\Flysystem\FilesystemException;
use SailCMS\Collection;
use SailCMS\Debug;
use SailCMS\Errors\FileException;
use SailCMS\Errors\ResponseTypeException;
use SailCMS\Middleware;
use SailCMS\Middleware\Data;
use SailCMS\Middleware\Http;
use SailCMS\Security;
use SailCMS\Templating\Engine;
use SailCMS\Types\MiddlewareType;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class Response
{
    private string $type = '';
    private object $data;
    private Collection $csvData;
    private Collection $columns;

    public string $template = '';
    public bool $compress = false;
    public bool $secure = false;
    public string $renderer = 'twig';
    private bool $rendererSet = false;

    private array $validTypes = ['text/html', 'application/json', 'text/csv', 'text/plain'];

    /**
     *
     * @throws ResponseTypeException
     *
     */
    public function __construct(string $type = 'text/html')
    {
        if (in_array($type, $this->validTypes, true)) {
            $this->compress = env('use_compression', 'false') === 'true';

            $this->type = $type;
            $this->data = new \stdClass;
            $this->columns = Collection::init();
            $this->csvData = Collection::init();
        } else {
            throw new ResponseTypeException("Type {$type} is not a valid response type. Valid response types are: text/html, application/json or text/csv");
        }
    }

    /**
     *
     * Set the type for the response (only used when using the AppController response property)
     *
     * @param  string  $type
     * @return void
     *
     */
    public function setType(string $type): void
    {
        switch (strtolower($type)) {
            default:
            case 'html':
                $this->type = 'text/html';
                break;

            case 'csv':
                $this->type = 'text/csv';
                break;

            case 'json':
                $this->type = 'application/json';
                break;
        }
    }

    public function useRenderer(string $renderer): void
    {
        if (Engine::rendererCheck($renderer)) {
            $this->renderer = $renderer;
            $this->rendererSet = true;
        }
    }

    /**
     *
     * Create an instance for a text/html response
     *
     * @return Response
     *
     */
    public static function html(): Response
    {
        return new Response('text/html');
    }

    /**
     *
     * Create an instance for a json response
     *
     * @return Response
     *
     */
    public static function json(): Response
    {
        return new Response('application/json');
    }

    /**
     *
     * Create an instance for a csv response
     *
     * @return Response
     *
     */
    public static function csv(): Response
    {
        return new Response('text/csv');
    }

    /**
     *
     * Set a key/value for templating or json output
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     *
     */
    public function set(string $key, mixed $value): void
    {
        $this->data->{$key} = $value;
    }

    /**
     *
     * Set many key/value pairs at once
     *
     * @param  array  $keyValues
     * @return void
     *
     */
    public function setArray(array $keyValues): void
    {
        foreach ($keyValues as $key => $value) {
            $this->data->{$key} = $value;
        }
    }

    /**
     *
     * Set the columns for a csv
     *
     * @param  array  $columns
     * @return void
     *
     */
    public function setColumns(array $columns): void
    {
        $this->columns->pushSpread(...$columns);
    }

    /**
     *
     * Set the data for CSV
     *
     * @param  array  $entries
     * @return void
     *
     */
    public function addRows(array $entries): void
    {
        $this->csvData->pushSpread(...$entries);
    }

    /**
     *
     * Add a single row to the CSV
     *
     * @param  array  $entry
     * @return void
     *
     */
    public function addRow(array $entry): void
    {
        $this->csvData->push($entry);
    }

    /**
     *
     * Render the response
     *
     * @param  bool  $executeMiddleware
     * @return void
     * @throws JsonException|FileException|FilesystemException|LoaderError|RuntimeError|SyntaxError
     *
     */
    public function render(bool $executeMiddleware = true): void
    {
        if (!$this->rendererSet) {
            // Twig by default, if nothing was requested, check config and use that.
            $masterValue = strtolower(setting('templating.renderer', 'twig'));
            $renderer = Engine::getRenderer($masterValue);
        } else {
            $renderer = Engine::getRenderer($this->renderer);
        }

        // Custom renderer's content type
        if ($renderer !== null) {
            header($renderer->contentType());
        } else {
            if ($this->secure) {
                header('Content-type: text/plain; charset=utf-8');
            } else {
                header('Content-type: ' . $this->type . '; charset=utf-8');
            }
        }

        // Enable compression
        if (env('use_compression', false)) {
            ob_start('ob_gzhandler');
        }

        // Before we render
        if ($executeMiddleware) {
            $result = Middleware::execute(MiddlewareType::HTTP, new Data(Http::BeforeRender, data: $this->data));
            $this->data = $result->data;
        }

        switch ($this->type) {
            default:
            case 'text/html':
                $engine = new Engine();

                if ($renderer !== null) {
                    $twig = $renderer->useTwig();

                    if ($twig) {
                        // Leverage Twig templating to build output document
                        echo $engine->render($this->template, $this->data, $this->renderer);
                    } else {
                        // Custom rendering
                        $st = microtime(true);

                        ob_start();
                        echo $renderer->render($this->template, $this->data);
                        $out = ob_get_clean();
                        Debug::view($this->template, (array)$this->data, $st);
                        echo $out;
                    }
                } else {
                    // Default (twig)
                    echo $engine->render($this->template, $this->data, $this->renderer);
                }
                break;

            case 'application/json':
                if (setting('devMode', false)) {
                    $json = json_encode($this->data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
                } else {
                    $json = json_encode($this->data, JSON_THROW_ON_ERROR);
                }

                if ($this->secure) {
                    echo base64_encode(Security::encrypt($json));
                } else {
                    echo $json;
                }
                break;

            case 'text/csv':
                $head = $this->columns->flatten(',', true);
                $body = '';


                $this->csvData->each(function ($key, $value) use (&$body)
                {
                    if ($body !== '') {
                        $body .= "\n";
                    }

                    if (is_array($value)) {
                        $col = new Collection($value);
                        $body .= $col->flatten(',', true);
                    } else {
                        $body .= $value->flatten(',', true);
                    }
                });

                $csv = $head . "\n";
                $csv .= $body;

                if ($this->secure) {
                    echo base64_encode(Security::encrypt($csv));
                } else {
                    echo $csv;
                }

                break;
        }

        if (env('use_compression', false)) {
            ob_end_flush();
        }
    }
}