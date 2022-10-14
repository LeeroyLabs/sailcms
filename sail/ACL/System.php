<?php

namespace SailCMS\ACL;

use SailCMS\Collection;
use SailCMS\Types\ACL;
use SailCMS\Types\ACLType;

class System
{
    public static function getAll(): Collection
    {
        return new Collection([
            // Roles
            new ACL('Role', ACLType::READ_WRITE),
            new ACL('Role', ACLType::READ)
        ]);
    }
}