<?php

namespace SailCMS\GraphQL;

use \GraphQL\Type\Definition\Type;

class Argument
{
    private string $name;
    private bool $optional = false;
    private Type $type;

    public function __construct(string $name, Type $type, bool $optional = false)
    {
    }
}