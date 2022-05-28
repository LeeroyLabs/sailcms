<?php

namespace SailCMS\Http;

use SailCMS\Collection;
use SailCMS\Errors\ResponseTypeException;
use SailCMS\Templating\Engine;

class Response
{
    private string $type = '';
    private object $data;
    private Collection $csvData;
    private Collection $columns;

    public string $template = '';
    public bool $compress = false;

    private array $validTypes = ['text/html', 'application/json', 'text/csv'];

    /**
     *
     * @throws ResponseTypeException
     *
     */
    public function __construct(string $type = 'text/html')
    {
        if (in_array($type, $this->validTypes, true)) {
            $this->compress = $_ENV['USE_COMPRESSION'] ?? false;

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
     * @param string $key
     * @param mixed $value
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
     * @param array $columns
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
     * @param array $entries
     * @return void
     *
     */
    public function setData(array $entries): void
    {
        $this->csvData->pushSpread(...$entries);
    }

    /**
     *
     * Render the response
     *
     * @return void
     *
     * @throws \JsonException
     *
     */
    public function render()
    {
        header('Content-type: ' . $this->type);

        if ($_ENV['USE_COMPRESSION']) {
            ob_start('ob_gzhandler');
        }

        switch ($this->type) {
            case 'text/html':
                $engine = new Engine();
                echo $engine->render($this->template, $this->data);

                // TODO RENDER WITH TWIG
                echo "HTML RENDER HERE !!!";
                break;

            case 'application/json':
                if ($_ENV['settings']->get('devMode')) {
                    echo json_encode($this->data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
                } else {
                    echo json_encode($this->data, JSON_THROW_ON_ERROR);
                }
                break;

            case 'text/csv':
                // TODO: RENDER CSV
                break;
        }

        if ($_ENV['USE_COMPRESSION']) {
            ob_end_flush();
        }
    }
}