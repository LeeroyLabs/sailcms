<?php

namespace SailCMS\ACL;

use SailCMS\Collection;
use SailCMS\Types\ACL;
use SailCMS\Types\ACLType;

class System
{
    public const RESERVED = ['role', 'user'];

    public static function getAll(): Collection
    {
        return new Collection([
            // Roles
            new ACL('Role', ACLType::READ_WRITE),
            new ACL('Role', ACLType::READ),

            // Users
            new ACL('User', ACLType::READ_WRITE),
            new ACL('User', ACLType::READ)
        ]);
    }
}