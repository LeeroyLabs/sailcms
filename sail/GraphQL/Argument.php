<?php

namespace SailCMS\GraphQL;

use \GraphQL\Type\Definition\Type;

class Argument
{
    public function __construct(public readonly string $name, public readonly Type $type, public readonly bool $optional = false)
    {
    }
}