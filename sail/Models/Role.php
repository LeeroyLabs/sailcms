<?php

namespace SailCMS\Models;

use MongoDB\BSON\ObjectId;
use SailCMS\ACL;
use SailCMS\Collection;
use SailCMS\Database\BaseModel;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Text;

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
     *
     */
    public function add(string $name, string $description, Collection|array $permissions): string
    {
        if (ACL::hasPermission(User::$currentUser, ACL::write('role'))) {
            return $this->insert([
                'name' => $name,
                'slug' => Text::kebabCase(Text::deburr($name)),
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
    public function update(string|ObjectId $id, string $name, int $level, string $description, Collection|array $permissions): bool
    {
        if (ACL::hasPermission(User::$currentUser, ACL::write('role'))) {
            $id = $this->ensureObjectId($id);

            // We do not allow for a role to be higher than 950 (Admin) (super admin is 1000)
            if ($level >= 950) {
                $level = 900;
            }

            $this->updateOne(
                ['_id' => $id],
                [
                    '$set' => [
                        'name' => $name,
                        'slug' => Text::kebabCase(Text::deburr($name)),
                        'description' => $description,
                        'level' => $level,
                        'permissions' => $permissions
                    ]
                ]
            );

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
            $this->deleteById($id);
        }

        return false;
    }

    /**
     *
     * Get list of available roles
     *
     * @return Collection
     * @throws DatabaseException
     * @throws ACLException
     *
     */
    public function list(): Collection
    {
        if (ACL::hasPermission(User::$currentUser, ACL::read('role'), ACL::readwrite('role'))) {
            return new Collection((array)$this->find([])->exec());
        }

        return new Collection([]);
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
}