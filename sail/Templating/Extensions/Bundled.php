<?php

namespace SailCMS\Templating\Extensions;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class Bundled extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('debug', [$this, 'debug']),
            new TwigFunction('env', [$this, 'env'])
        ];
    }

    public function getFilters(): array
    {
        return [];
    }

    public function debug(mixed $data): void
    {
        echo '<pre>';
        print_r($data);
        echo '</pre>';
    }

    public function env(string $key): string|array|bool
    {
        return getenv($key);
    }

}