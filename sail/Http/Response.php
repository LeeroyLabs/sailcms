<?php

namespace SailCMS\Http;

use JsonException;
use League\Flysystem\FilesystemException;
use SailCMS\Collection;
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

    private array $validTypes = ['text/html', 'application/json', 'text/csv', 'text/plain'];

    /**
     *
     * @throws ResponseTypeException
     *
     */
    public function __construct(string $type = 'text/html')
    {
        if (in_array($type, $this->validTypes, true)) {
            $this->compress = env('use_compression', false);

            $this->type = $type;
            $this->data = new \stdClass;
            $this->columns = new Collection([]);
            $this->csvData = new Collection([]);
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
        if ($this->secure) {
            header('Content-type: text/plain; charset=utf-8');
        } else {
            header('Content-type: ' . $this->type . '; charset=utf-8');
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
            case 'text/html':
                $engine = new Engine();
                echo $engine->render($this->template, $this->data);
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