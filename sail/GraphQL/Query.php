<?php

namespace SailCMS\GraphQL;

use GraphQL\Type\Definition\Type;

class Query
{
    public string $name;
    public array $resolver;
    public array $args;
    public Type $returnValue;

    public function __construct(string $name, array $resolver, Type $returnValue, array $args = [])
    {
        $ctrl = new $resolver[0]();
        
        $this->name = $name;
        $this->resolver = [$ctrl, $resolver[1]];
        $this->args = $args;
        $this->returnValue = $returnValue;
    }

    /**
     *
     * Little utility
     *
     * @param string $name
     * @param array $resolver
     * @param Type $returnValue
     * @param array $args
     * @return Query
     *
     */
    public static function init(string $name, array $resolver, Type $returnValue, array $args = []): Query
    {
        return new Query($name, $resolver, $returnValue, $args);
    }
}