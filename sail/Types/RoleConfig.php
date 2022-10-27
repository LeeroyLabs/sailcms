<?php

namespace SailCMS\Types;

use SailCMS\Collection;
use SailCMS\Models\Role;

class RoleConfig
{
    public function __construct(
        public readonly Role $role,
        public readonly Collection $allowedPermissions,
        public readonly Collection $permissions
    )
    {
    }
}