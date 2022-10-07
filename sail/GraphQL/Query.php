<?php

namespace SailCMS\GraphQL;

use GraphQL\Type\Definition\Type;

class Query
{
    public string $name;
    public array $resolver;
    public array $args;
    public Type $returnValue;

    public function __construct(string $operation, array $resolver, array $args, Type $returnValue)
    {
        $ctrl = new $resolver[0]();

        $this->name = $operation;
        $this->resolver = [$ctrl, $resolver[1]];
        $this->args = $args;
        $this->returnValue = $returnValue;
    }

    /**
     *
     * Little utility
     *
     * @param  string  $operation
     * @param  array   $resolver
     * @param  Type    $returnValue
     * @param  array   $args
     * @return Query
     *
     */
    public static function init(string $operation, array $resolver, array $args, Type $returnValue): Query
    {
        return new Query($operation, $resolver, $args, $returnValue);
    }
}