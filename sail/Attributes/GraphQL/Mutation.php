<?php

namespace SailCMS\Attributes\GraphQL;

use Attribute;

#[Attribute]
class Mutation
{
    public function __construct(public string $mutation)
    {
    }
}