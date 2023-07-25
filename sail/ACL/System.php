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
        'register',
        'navigation',
        'group'
    ];

    public static function getAll(): Collection
    {
        return new Collection([
            // Roles
            new ACL('Role', ACLType::READ_WRITE, 'Manage user roles', 'Gestion des roles utilisateurs'),
            new ACL('Role', ACLType::READ, 'Read access for roles', 'Accès lecture aux roles'),

            // Users
            new ACL('User', ACLType::READ_WRITE, 'Manage users', 'Gestion utilisateurs'),
            new ACL('User', ACLType::READ, 'Read access for users', 'Accès lecture aux utilisateurs'),

            // Entry
            new ACL('EntryType', ACLType::READ_WRITE, 'Manage entry types', 'Gestion des types d\'entrées'),
            new ACL('EntryType', ACLType::READ, 'Read access entry types', 'Accès lecture aux types d\'entrées'),
            new ACL('EntryLayout', ACLType::READ_WRITE, 'Manage entry layouts', 'Gestion de la mise en page des entrées'),
            new ACL('EntryLayout', ACLType::READ, 'Read access to entry layouts', 'Accès lecture aux mise en page des entrées'),
            new ACL('EntrySeo', ACLType::READ_WRITE, 'Manage entry SEO', 'Gestion du SEO des entrées'),
            new ACL('EntrySeo', ACLType::READ, 'Read access to entry SEO', 'Accès lecture aux SEO des entrées'),
            new ACL('EntryVersion', ACLType::READ_WRITE, 'Manage entry versions', 'Gestion des version des entrées'),
            new ACL('EntryVersion', ACLType::READ, 'Read access to entry versions', 'Accès lecture aux versions des entrées'),
            new ACL('EntryPublication', ACLType::READ_WRITE, 'Manage entry publications', 'Gestion des publications des entrées'),
            new ACL('EntryPublication', ACLType::READ, 'Read access to entry publications', 'Accès lecture aux publications des entrées'),
            new ACL('EntryFields', ACLType::READ_WRITE, 'Manage entry fields', 'Gestion des champs'),
            new ACL('EntryFields', ACLType::READ, 'Read access to entry fields', 'Accès lecture des champs'),

            // Assets
            new ACL('Asset', ACLType::READ_WRITE, 'Manage assets', 'Gestion des actifs'),
            new ACL('Asset', ACLType::READ, 'Read access to assets', 'Accès lecture aux actifs'),

            // Emails
            new ACL('Emails', ACLType::READ_WRITE, 'Manage emails', 'Gestion des courriels'),
            new ACL('Emails', ACLType::READ, 'Read access to emails', 'Accès lecture des courriels'),

            // Categories
            new ACL('Category', ACLType::READ_WRITE, 'Manage categories', 'Gestion des catégories'),
            new ACL('Category', ACLType::READ, 'Read access to categories', 'Accès lecture aux catégories'),

            // Register
            new ACL('Register', ACLType::READ, 'Read access to register', 'Accès lecture au registre'),

            // Navigation
            new ACL('Navigation', ACLType::READ_WRITE, 'Manage navigations', 'Gestion des navigations'),
            new ACL('Navigation', ACLType::READ, 'Read access to navigations', 'Accès lecture aux navigations'),

            // Groups
            new ACL('Group', ACLType::READ_WRITE, 'Manage user groups', 'Gestion des groupes utilisateurs'),
            new ACL('Group', ACLType::READ, 'Read access to user groups', 'Accès lecture aux groupes utilisateurs'),

            // Tasks
            new ACL('Task', ACLType::READ_WRITE, 'Manage system tasks', 'Gestion des tâches système'),
            new ACL('Task', ACLType::READ, 'Read access to system tasks', 'Accès lecture aux tâches système'),
        ]);
    }
}