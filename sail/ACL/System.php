<?php

namespace SailCMS\ACL;

use SailCMS\Collection;
use SailCMS\Types\ACL;
use SailCMS\Types\ACLType;

class System
{
    public const RESERVED = ['role', 'user', 'entry_type', 'entry_type_layout'];

    public static function getAll(): Collection
    {
        return new Collection([
            // Roles
            new ACL('Role', ACLType::READ_WRITE),
            new ACL('Role', ACLType::READ),

            // Users
            new ACL('User', ACLType::READ_WRITE),
            new ACL('User', ACLType::READ),

            // Entry
            new ACL('EntryType', ACLType::READ_WRITE),
            new ACL('EntryType', ACLType::READ),
            new ACL('EntryTypeLayout', ACLType::READ_WRITE),
            new ACL('EntryTypeLayout', ACLType::READ),
        ]);
    }
}