<?php

namespace SailCMS\Models;

use MongoDB\BSON\ObjectId;
use RuntimeException;
use SailCMS\ACL;
use SailCMS\Collection;
use SailCMS\Database\BaseModel;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Text;
use SailCMS\Types\RoleConfig;

class Role extends BaseModel
{
    public string $name;
    public string $slug;
    public string $description;
    public int $level;
    public Collection $permissions;

    public function fields(bool $fetchAllFields = false): array
    {
        return ['_id', 'name', 'slug', 'description', 'level', 'permissions'];
    }

    /**
     *
     * Add a role
     *
     * @param  string            $name
     * @param  string            $description
     * @param  Collection|array  $permissions
     * @return string
     * @throws DatabaseException
     * @throws ACLException
     * @throws RuntimeException
     *
     */
    public function create(string $name, string $description, Collection|array $permissions): string
    {
        if (ACL::hasPermission(User::$currentUser, ACL::write('role'))) {
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

            return $this->insert([
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'permissions' => $permissions
            ]);
        }

        return '';
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
     *
     */
    public function update(string|ObjectId $id, string $name = '', int $level = -1, string $description = '', Collection|array $permissions = []): bool
    {
        if (ACL::hasPermission(User::$currentUser, ACL::write('role'))) {
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

        return false;
    }

    /**
     *
     * Remove a role
     *
     * @param  string|ObjectId  $id
     * @return bool
     * @throws DatabaseException
     * @throws ACLException
     *
     */
    public function remove(string|ObjectId $id): bool
    {
        if (ACL::hasPermission(User::$currentUser, ACL::write('role'))) {
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
     *
     */
    public function list(): Collection
    {
        if (ACL::hasPermission(User::$currentUser, ACL::read('role'), ACL::readwrite('role'))) {
            $userRoles = User::$currentUser->roles;
            $roles = new Collection($this->find([])->exec());

            return $roles->intersect($userRoles);
        }

        return new Collection([]);
    }

    /**
     *
     * Get a role and the set of possible permissions to add (based on user's ACL)
     *
     * @param  string|ObjectId  $id
     * @return RoleConfig|null
     * @throws ACLException
     * @throws DatabaseException
     *
     */
    public function getById(string|ObjectId $id): ?RoleConfig
    {
        if (ACL::hasPermission(User::$currentUser, ACL::read('role'), ACL::readwrite('role'))) {
            $role = $this->findById($id);

            $permissionList = ACL::getList();
            $userPermissions = $userRoles = User::$currentUser->roles;

            if ($userPermissions->length === 1 && $userPermissions->at(0) === '*') {
                $userPermissions = $permissionList;
            }

            return new RoleConfig($role, $userPermissions, $permissionList);
        }

        return null;
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
        $roles = new Collection($instance->find(['slug' => ['$in' => $list]])->exec());
        return $roles->maxBy('level');
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