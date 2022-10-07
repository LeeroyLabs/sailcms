<?php

namespace SailCMS\Templating\Extensions;

use SailCMS\Sail;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class Bundled extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('debug', [$this, 'debug']),
            new TwigFunction('env', [$this, 'env']),
            new TwigFunction('publicPath', [$this, 'publicPath'])
        ];
    }

    public function getFilters(): array
    {
        return [];
    }

    /**
     *
     * Debug your variable within Twig
     *
     * @param  mixed  $data
     * @return void
     *
     */
    public function debug(mixed $data): void
    {
        echo '<pre>';
        print_r($data);
        echo '</pre>';
    }

    /**
     *
     * Get an .env variable
     *
     * @param  string  $key
     * @return string|array|bool
     *
     */
    public function env(string $key): string|array|bool
    {
        return getenv($key);
    }

    /**
     *
     * Get the public directory for assets
     *
     * @return string
     *
     */
    public function publicPath(): string
    {
        return '/public/' . Sail::currentApp();
    }
}