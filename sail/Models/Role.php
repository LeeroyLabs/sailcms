<?php

namespace SailCMS\Models;

use SailCMS\Collection;
use SailCMS\Database\BaseModel;
use SailCMS\Errors\DatabaseException;

class Role extends BaseModel
{
    public function fields(): array
    {
        return ['_id', 'name', 'slug', 'permissions'];
    }

    /**
     *
     * Get all roles in a convenient way
     *
     * @return Collection
     * @throws DatabaseException
     *
     */
    public static function getAll(): Collection
    {
        $instance = new static();
        $roles = $instance->find([])->exec();
        return new Collection($roles);
    }
}