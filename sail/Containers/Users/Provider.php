<?php

namespace SailCMS\Containers\Users;

use SailCMS\Models\User;
use SailCMS\Errors\DatabaseException;

class Provider
{
    private User $model;

    public function __construct()
    {
        $this->model = new User();
    }

    /**
     *
     * Get a user by id
     *
     * @param string $id
     * @return User|null
     * @throws DatabaseException
     *
     */
    public function getUser(string $id): ?User
    {
        // TODO: Permissions check

        return $this->model->getById($id);
    }
}