<?php

namespace SailCMS\Http;

use finfo;
use SailCMS\Collection;
use SailCMS\Http\Input\Get;
use SailCMS\Http\Input\Post;
use SailCMS\Models\User;
use SailCMS\Types\UploadFile;

class Request
{
    private string $ip;
    private string $method;
    private string $uri;
    private string $_agent;
    private Post $_post;
    private Get $_get;
    private Collection $_headers;
    private ?User $user;

    public function __construct()
    {
        $this->ip = $this->getUserIp();
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'get';
        $this->uri = $_SERVER['REQUEST_URI'] ?? '/';
        $this->_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'CLI';
        $this->_post = new Post();
        $this->_get = new Get();
        $this->user = User::$currentUser;

        // Make sure all headers are lowercase
        $headers = getallheaders();
        $cleaned = [];

        foreach ($headers as $key => $value) {
            $cleaned[strtolower($key)] = $value;
        }

        $this->_headers = new Collection($cleaned);
    }

    /**
     *
     * Get a cookie from the request
     *
     * @param  string  $name
     * @return mixed
     *
     */
    public function cookie(string $name): mixed
    {
        return $_COOKIE[$name] ?? null;
    }

    /**
     *
     * Get an uploaded file
     *
     * @param  string  $name
     * @param  int     $index
     * @return UploadFile|null
     *
     */
    public function file(string $name, int $index = 0): ?UploadFile
    {
        if (!empty($_FILES[$name])) {
            if (is_array($_FILES[$name]['name'])) {
                // Get real file type
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $type = $finfo->file($_FILES[$name]['tmp_name'][$index]);

                return new UploadFile(
                    $_FILES[$name]['name'][$index],
                    $type,
                    $_FILES[$name]['tmp_name'][$index],
                    $_FILES[$name]['error'][$index],
                    $_FILES[$name]['size'][$index]
                );
            }

            // Get real file type
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $type = $finfo->file($_FILES[$name]['tmp_name']);

            return new UploadFile(
                $_FILES[$name]['name'],
                $type,
                $_FILES[$name]['tmp_name'],
                $_FILES[$name]['error'],
                $_FILES[$name]['size']
            );
        }

        return null;
    }

    /**
     *
     * Get access token if set
     *
     * @return string
     *
     */
    public function token(): string
    {
        return $this->header('x-access-token') ?? '';
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
     * Read the input buffer and return the raw data from it
     *
     * @return string
     *
     */
    public function input(): string
    {
        return file_get_contents('php://input');
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
        return $this->_headers->get(strtolower($key), '');
    }

    /**
     *
     * Does the request have the required header set and not empty
     *
     * @param  string  $key
     * @return bool
     *
     */
    public function hasHeader(string $key): bool
    {
        $h = $this->_headers->get(strtolower($key), '');
        return (!empty($h));
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

    /**
     *
     * Get active user (if any)
     *
     * @return User|null
     *
     */
    public function user(): ?User
    {
        return $this->user;
    }

    /**
     *
     * Is a user logged in
     *
     * @return bool
     *
     */
    public function isLoggedIn(): bool
    {
        return isset($this->user);
    }

    /**
     *
     * Figure out the ip strategy
     *
     * @return string
     *
     */
    private function getUserIp(): string
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