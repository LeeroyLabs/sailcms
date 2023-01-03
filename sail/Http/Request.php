<?php

namespace SailCMS\Http;

use SailCMS\Collection;
use SailCMS\Http\Input\Get;
use SailCMS\Http\Input\Post;

class Request
{
    private string $ip;
    private string $method;
    private string $uri;
    private string $_agent;
    private Post $_post;
    private Get $_get;
    private Collection $_headers;

    public function __construct()
    {
        $this->ip = $this->getUserIp();
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'get';
        $this->uri = $_SERVER['REQUEST_URI'] ?? '/';
        $this->_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'CLI';
        $this->_post = new Post();
        $this->_get = new Get();

        $headers = getallheaders();

        if (count($headers) === 1) {
            $headers = $_SERVER['HEADERS']; // Added by Sail Server
        }

        $this->_headers = new Collection($headers);
    }

    /**
     *
     * Get user IP
     *
     * @return string
     *
     */
    public function ipAddress(): string
    {
        return $this->ip;
    }

    /**
     *
     * Get HTTP method
     *
     * @return string
     *
     */
    public function httpMethod(): string
    {
        return $this->method;
    }

    /**
     *
     * URI
     *
     * @return string
     *
     */
    public function uri(): string
    {
        return $this->uri;
    }

    /**
     *
     * Get user agent
     *
     * @return string
     *
     */
    public function agent(): string
    {
        return $this->_agent;
    }

    /**
     *
     * Get user input using xss filtering
     *
     * @param  string      $key
     * @param  bool        $skip
     * @param  mixed|null  $default
     * @return mixed
     *
     */
    public function get(string $key, bool $skip = false, mixed $default = null): mixed
    {
        if ($skip) {
            return $_GET[$key] ?? $default;
        }

        return $this->_get->get($key, $default);
    }

    /**
     *
     * Post user input using xss filtering
     *
     * @param  string      $key
     * @param  bool        $skip
     * @param  mixed|null  $default
     * @return mixed
     *
     */
    public function post(string $key, bool $skip = false, mixed $default = null): mixed
    {
        if ($skip) {
            return $_POST[$key] ?? $default;
        }

        return $this->_post->get($key, $default);
    }

    /**
     *
     * Get a specific header
     *
     * @param  string  $key
     * @return mixed
     *
     */
    public function header(string $key): mixed
    {
        return $this->_headers->get($key);
    }

    /**
     *
     * Get all headers
     *
     * @return Collection
     *
     */
    public function headers(): Collection
    {
        return $this->_headers;
    }

    private function getUserIp()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }

        return $_SERVER['REMOTE_ADDR'] ?? '::1';
    }
}