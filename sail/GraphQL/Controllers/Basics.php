<?php

namespace SailCMS\GraphQL\Controllers;

use SailCMS\Attributes\GraphQL\Query;
use SailCMS\Sail;

class Basics
{
    #[Query('version')]
    public function version(): string
    {
        return Sail::SAIL_VERSION;
    }
}