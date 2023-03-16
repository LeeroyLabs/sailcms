<?php

namespace SailCMS\ACL;

use SailCMS\Collection;
use SailCMS\Types\ACL;
use SailCMS\Types\ACLType;

class System
{
    public const RESERVED = [
        'role',
        'user',
        'entrytype',
        'entrylayout',
        'entryseo',
        'entryversion',
        'entrypublication',
        'asset',
        'emails',
        'categories',
        'register'
    ];

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
            new ACL('EntryLayout', ACLType::READ_WRITE),
            new ACL('EntryLayout', ACLType::READ),
            new ACL('EntrySeo', ACLType::READ),
            new ACL('EntrySeo', ACLType::READ_WRITE),
            new ACL('EntryVersion', ACLType::READ),
            new ACL('EntryVersion', ACLType::READ_WRITE),
            new ACL('EntryPublication', ACLType::READ),
            new ACL('EntryPublication', ACLType::READ_WRITE),

            // Assets
            new ACL('Asset', ACLType::READ_WRITE),
            new ACL('Asset', ACLType::READ),

            // Emails
            new ACL('Emails', ACLType::READ_WRITE),
            new ACL('Emails', ACLType::READ),

            // Categories
            new ACL('Category', ACLType::READ_WRITE),
            new ACL('Category', ACLType::READ),

            // Register
            new ACL('Register', ACLType::READ)
        ]);
    }
}