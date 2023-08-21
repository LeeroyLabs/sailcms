<?php

namespace SailCMS\Attributes\GraphQL;

use Attribute;

#[Attribute]
class Query
{
    public function __construct(public string $query)
    {
    }
}