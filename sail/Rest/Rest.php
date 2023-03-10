<?php

namespace SailCMS\Rest;

use SailCMS\Rest\Controllers\Users;
use SailCMS\Routing\Router;

class Rest
{
    private static string $currentVersion = 'v1';

    public static function init(): void
    {
        $router = new Router();

        // Users
        $router->get(self::buildURL('/users/:id'), 'en', Users::class, 'user');
        $router->get(self::buildURL('/users'), 'en', Users::class, 'users');

        /**
         *
         * self::addQueryResolver('resendValidationEmail', Users::class, 'resendValidationEmail');
         * self::addMutationResolver('createUser', Users::class, 'createUser');
         * self::addMutationResolver('createUserGetId', Users::class, 'createUserGetId');
         * self::addMutationResolver('createAdminUser', Users::class, 'createAdminUser');
         * self::addMutationResolver('updateUser', Users::class, 'updateUser');
         * self::addMutationResolver('deleteUser', Users::class, 'deleteUser');
         * self::addMutationResolver('validateAccount', Users::class, 'validateAccount');
         *
         */

        // Authentication & Password related
        $router->post(self::buildURL('/auth'), 'en', Users::class, 'authenticate');
        $router->get(self::buildURL('/auth-token/:any'), 'en', Users::class, 'authenticateWithToken');
        $router->get(self::buildURL('/auth/:any'), 'en', Users::class, 'verifyAuthentication');
        $router->get(self::buildURL('/verify-twofactor/:id/:num'), 'en', Users::class, 'verifyTFA');
        $router->post(self::buildURL('/forgot-password'), 'en', Users::class, 'forgotPassword');
        $router->post(self::buildURL('/change-password'), 'en', Users::class, 'changePassword');

        // Roles & ACLs

        // Assets

        // Emails

        // Entries

        // Register

        // Categories

        // Misc
        /**
         * // Roles & ACL
         * self::addQueryResolver('role', Roles::class, 'role');
         * self::addQueryResolver('roles', Roles::class, 'roles');
         * self::addQueryResolver('acls', Roles::class, 'acls');
         * self::addMutationResolver('deleteRole', Roles::class, 'delete');
         *
         * // Assets
         * self::addQueryResolver('asset', Assets::class, 'asset');
         * self::addQueryResolver('assets', Assets::class, 'assets');
         * self::addMutationResolver('uploadAsset', Assets::class, 'createAsset');
         * self::addMutationResolver('updateAssetTitle', Assets::class, 'updateAssetTitle');
         * self::addMutationResolver('deleteAsset', Assets::class, 'deleteAsset');
         * self::addMutationResolver('transformAsset', Assets::class, 'transformAsset');
         *
         * // Emails
         * self::addQueryResolver('email', Emails::class, 'email');
         * self::addQueryResolver('emails', Emails::class, 'emails');
         * self::addMutationResolver('createEmail', Emails::class, 'createEmail');
         * self::addMutationResolver('updateEmail', Emails::class, 'updateEmail');
         * self::addMutationResolver('deleteEmail', Emails::class, 'deleteEmail');
         * self::addMutationResolver('deleteEmailBySlug', Emails::class, 'deleteEmailBySlug');
         *
         * // Entries
         * self::addQueryResolver('homepageEntry', Entries::class, 'homepageEntry');
         *
         * self::addQueryResolver('entryTypes', Entries::class, 'entryTypes');
         * self::addQueryResolver('entryType', Entries::class, 'entryType');
         * self::addMutationResolver('createEntryType', Entries::class, 'createEntryType');
         * self::addMutationResolver('updateEntryType', Entries::class, 'updateEntryType');
         * self::addMutationResolver('deleteEntryType', Entries::class, 'deleteEntryType');
         *
         * self::addQueryResolver('entries', Entries::class, 'entries');
         * self::addQueryResolver('entry', Entries::class, 'entry');
         * self::addMutationResolver('createEntry', Entries::class, 'createEntry');
         * self::addMutationResolver('updateEntrySeo', Entries::class, 'updateEntrySeo');
         * self::addMutationResolver('updateEntry', Entries::class, 'updateEntry');
         * self::addMutationResolver('deleteEntry', Entries::class, 'deleteEntry');
         *
         * self::addQueryResolver('entryVersion', Entries::class, 'entryVersion');
         * self::addQueryResolver('entryVersions', Entries::class, 'entryVersions');
         * self::addMutationResolver('applyVersion', Entries::class, 'applyVersion');
         *
         * self::addQueryResolver('entryLayout', Entries::class, 'entryLayout');
         * self::addQueryResolver('entryLayouts', Entries::class, 'entryLayouts');
         * self::addMutationResolver('createEntryLayout', Entries::class, 'createEntryLayout');
         * self::addMutationResolver('updateEntryLayoutSchema', Entries::class, 'updateEntryLayoutSchema');
         * self::addMutationResolver('updateEntryLayoutSchemaKey', Entries::class, 'updateEntryLayoutSchemaKey');
         * self::addMutationResolver('deleteEntryLayout', Entries::class, 'deleteEntryLayout');
         *
         * self::addQueryResolver('fields', Entries::class, 'fields');
         *
         * // Register
         * self::addQueryResolver('registeredExtensions', Registers::class, 'registeredExtensions');
         *
         * // Categories
         * self::addQueryResolver('category', Categories::class, 'category');
         * self::addQueryResolver('categoryBySlug', Categories::class, 'categoryBySlug');
         * self::addQueryResolver('categoryFullTree', Categories::class, 'categoryFullTree');
         * self::addQueryResolver('categoryEntries', Categories::class, 'categoryEntries');
         * self::addMutationResolver('createCategory', Categories::class, 'createCategory');
         * self::addMutationResolver('updateCategory', Categories::class, 'updateCategory');
         * self::addMutationResolver('updateCategoryOrders', Categories::class, 'updateCategoryOrders');
         * self::addMutationResolver('deleteCategory', Categories::class, 'deleteCategory');
         * self::addMutationResolver('deleteCategoryBySlug', Categories::class, 'deleteCategoryBySlug');
         *
         * // Misc calls
         * // TODO: GET LOGS (from file or db)
         */
    }

    /**
     *
     * Build a rest url with current version
     *
     * @param  string  $url
     * @return string
     *
     */
    private static function buildURL(string $url): string
    {
        return '/rest/' . self::$currentVersion . $url;
    }
}