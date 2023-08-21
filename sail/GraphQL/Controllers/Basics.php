<?php

namespace SailCMS\GraphQL\Controllers;

use SailCMS\Sail;

class Basics
{
    public function version(): string
    {
        return Sail::SAIL_VERSION;
    }
}