<?php

namespace SailCMS\Attributes\GraphQL;

use Attribute;

#[Attribute]
class CustomResolver
{
    public function __construct(public string $type)
    {
    }
}