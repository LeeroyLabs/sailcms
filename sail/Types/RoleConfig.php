<?php

namespace SailCMS\Types;

use SailCMS\Collection;
use SailCMS\Models\Role;

class RoleConfig implements \JsonSerializable
{
    public function __construct(
        public readonly Role $role,
        public readonly Collection $allowedPermissions,
        public readonly Collection $permissions
    ) {
    }

    /**
     *
     * serialize when using json_encode
     *
     * @return mixed
     * @throws \JsonException
     *
     */
    public function jsonSerialize(): mixed
    {
        return [
            'role' => $this->role->toJSON(true),
            'allowedPermissions' => $this->allowedPermissions->unwrap(),
            'permissions' => $this->permissions->unwrap()
        ];
    }
}