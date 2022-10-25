<?php

namespace SailCMS\GraphQL\Types;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use SailCMS\GraphQL\Types as GTypes;

class General
{
    public static function pagination(): Type
    {
        return new ObjectType([
            'name' => 'Pagination',
            'fields' => [
                'current' => ['type' => GTypes::int()],
                'totalPages' => ['type' => GTypes::int()],
                'total' => ['type' => GTypes::int()]
            ]
        ]);
    }
}