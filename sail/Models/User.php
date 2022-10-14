<?php

namespace SailCMS\Models;

use SailCMS\Database\BaseModel;
use SailCMS\Errors\DatabaseException;

class User extends BaseModel
{
    public static User $currentUser;

    public static function authenticate(): void
    {
        static::$currentUser = new User();
    }

    public function fields(): array
    {
        return ['_id', 'name', 'role', 'email', 'status', 'avatar'];
    }

    /**
     *
     * Get user by id
     *
     * @param  string  $id
     * @return User|null
     *
     * @throws DatabaseException
     *
     */
    public static function byId(string $id): ?User
    {
        $instance = new static();
        return $instance->findById($id)->exec();
    }

    /**
     *
     * Get User by id
     *
     * @param  string  $id
     * @return User|null
     * @throws DatabaseException
     *
     */
    public function getById(string $id): ?User
    {
        return $this->findById($id)->exec();
    }
}