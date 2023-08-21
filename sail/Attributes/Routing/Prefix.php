<?php

namespace SailCMS\Attributes\Routing;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class Prefix
{
    public function __construct(public string $prefix = '')
    {
    }
}