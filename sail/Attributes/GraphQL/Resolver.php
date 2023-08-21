<?php

namespace SailCMS\Attributes\GraphQL;

use Attribute;

#[Attribute]
class Resolver
{
    public function __construct(public string $type)
    {
    }
}