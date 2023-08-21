<?php

namespace SailCMS\Attributes\Routing;

use Attribute;
use SailCMS\Text;
use SailCMS\Types\Http;

#[Attribute(\Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
readonly class Route
{
    public string $name;

    public function __construct(public string $route, public Http $http = Http::GET, public string $locale = 'en', string $name = '')
    {
        if (empty(trim($name))) {
            // Create a name from the route and locale
            $this->name = Text::from($route)->slug()->snake()->concat('_' . $locale)->value();
        } else {
            $this->name = $name;
        }
    }
}