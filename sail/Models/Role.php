<?php

namespace SailCMS\Models;

use Exception;
use MongoDB\BSON\ObjectId;
use RuntimeException;
use SailCMS\ACL;
use SailCMS\Collection;
use SailCMS\Database\Model;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\PermissionException;
use SailCMS\Text;
use SailCMS\Types\QueryOptions;
use SailCMS\Types\RoleConfig;

/**
 *
 * @property string     $name
 * @property string     $slug
 * @property string     $description
 * @property int        $level
 * @property Collection $permissions
 *
 */
class Role extends Model
{
    protected string $collection = 'roles';
    protected string $permissionGroup = 'role';

    protected array $casting = [
        'permissions' => Collection::class
    ];

    /**
     *
     * Add a role
     *
     * @param  string            $name
     * @param  string            $description
     * @param  Collection|array  $permissions
     * @return bool
     * @throws DatabaseException
     * @throws ACLException
     * @throws RuntimeException
     * @throws PermissionException
     *
     */
    public function create(string $name, string $description, Collection|array $permissions): bool
    {
        $this->hasPermissions();

        $totalACL = ACL::count();
        $slug = Text::slugify($name);

        // Validate, if not usable, throw an exception
        $this->usable($slug, '', true);

        if (is_array($permissions)) {
            $requestedACL = count($permissions);
            $permissions = new Collection($permissions);
        } else {
            $requestedACL = $permissions->length;
        }

        if ($requestedACL === $totalACL) {
            $permissions = new Collection(['*']);
        }

        try {
            $this->insert([
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'permissions' => $permissions
            ]);

            return true;
        } catch (Exception) {
            return false;
        }
    }

    /**
     *
     * Update a role
     *
     * @param  string|ObjectId   $id
     * @param  string            $name
     * @param  int               $level
     * @param  string            $description
     * @param  Collection|array  $permissions
     * @return bool
     * @throws DatabaseException
     * @throws ACLException
     * @throws PermissionException
     *
     */
    public function update(string|ObjectId $id, string $name = '', int $level = -1, string $description = '', Collection|array $permissions = []): bool
    {
        $this->hasPermissions();

        $id = $this->ensureObjectId($id);
        $totalACL = ACL::count();
        $update = [];

        // We do not allow for a role to be higher than 950 (Admin) (super admin is 1000)
        if ($level > -1) {
            if ($level >= 950) {
                $level = 900;
            }

            $update['level'] = $level;
        }

        if (!empty($permissions)) {
            if (is_array($permissions)) {
                $requestedACL = count($permissions);
                $permissions = new Collection($permissions);
            } else {
                $requestedACL = $permissions->length;
            }

            if ($requestedACL === $totalACL) {
                $permissions = new Collection(['*']);
            }

            $update['permissions'] = $permissions;
        }

        if (trim($name) !== '') {
            $update['name'] = trim($name);
        }

        if (trim($description)) {
            $update['description'] = trim($description);
        }

        $this->updateOne(['_id' => $id], ['$set' => $update]);
        return true;
    }

    /**
     *
     * Remove a role
     *
     * @param  string|ObjectId  $id
     * @return bool
     * @throws DatabaseException
     * @throws ACLException
     * @throws PermissionException
     *
     */
    public function delete(string|ObjectId $id): bool
    {
        $this->hasPermissions();
        $role = $this->findById($id)->exec();

        if ($role) {
            $slug = $role->slug;

            if ($slug !== 'super-administrator' && $slug !== 'administrator') {
                // Update All users (remove this role from them)
                User::removeRoleFromAll($slug);
                $this->deleteById($id);
                return true;
            }
        }

        return false;
    }

    /**
     *
     * Get list of available roles
     *
     * @return Collection
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function list(): Collection
    {
        $this->hasPermissions(true);

        $userRoles = User::$currentUser->roles;
        $highest = self::getHighestLevel($userRoles);
        $list = $this->find(['level' => ['$lte' => $highest]], QueryOptions::initWithSort(['level' => -1]))->exec();

        return new Collection($list);
    }

    /**
     *
     * Get a role and the set of possible permissions to add (based on user's ACL)
     *
     * @param  string|ObjectId  $id
     * @return RoleConfig|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function getById(string|ObjectId $id): ?RoleConfig
    {
        $this->hasPermissions(true);

        $role = $this->findById($id)->exec();

        $permissionList = ACL::getList();
        $userPermissions = User::$currentUser->roles;

        if ($userPermissions->length === 1 && $userPermissions->at() === '*') {
            $userPermissions = $permissionList;
        }

        return new RoleConfig($role, $userPermissions, $permissionList);
    }

    /**
     *
     * Get a role by its name
     *
     * @param  string  $role
     * @return ?Role
     * @throws DatabaseException
     *
     */
    public function getByName(string $role): ?Role
    {
        return $this->findOne(['slug' => strtolower(Text::deburr($role))])->exec();
    }

    /**
     *
     * Get the highest leveled role from the list
     *
     * @param  Collection|array  $list
     * @return int
     * @throws DatabaseException
     *
     */
    public static function getHighestLevel(Collection|array $list): int
    {
        if (is_object($list)) {
            $list = $list->unwrap();
        }

        $instance = new static();
        return (new Collection($instance->find(['slug' => ['$in' => $list]])->exec()))->maxBy('level');
    }

    /**
     *
     * Make sure we can use the given slug
     *
     * @param  string  $slug
     * @param  string  $id
     * @param  bool    $throw
     * @return bool
     * @throws DatabaseException
     *
     */
    private function usable(string $slug, string $id = '', bool $throw = false): bool
    {
        $found = $this->findOne(['slug' => $slug])->exec();

        if ($id === '' && $found) {
            if ($throw) {
                throw new RuntimeException("Cannot use role with name '{$slug}', it's already in use.");
            }

            return false;
        }

        return true;
    }
}