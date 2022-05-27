<?php

namespace SailCMS\Models;

use SailCMS\Errors\DatabaseException;
use SailCMS\Types\ContainerInformation;
use SailCMS\Database\BaseModel;

class Containers extends BaseModel
{
    public function fields(): array
    {
        return ['_id', 'name', 'classname', 'version', 'semver'];
    }

    /**
     *
     * Register a container if it's not registered yet
     *
     * @throws DatabaseException
     *
     */
    public function register(string $className, ContainerInformation $info): bool
    {
        $record = $this->findOne(['name' => $info->name])->exec();

        if (!$record) {
            $this->insert([
                'name' => $info->name,
                'classname' => $className,
                'version' => $info->version,
                'semver' => $info->semver,
                'sites' => $info->sites->unwrap()
            ]);

            return true;
        }

        if ($record?->version !== $info->version || $record?->semver !== $info->semver) {
            // Update record, mismatched
            $this->updateOne(['name' => $info->name], [
                '$set' => [
                    'version' => $info->version,
                    'semver' => $info->semver
                ]
            ]);
        }

        return false;
    }

    /**
     *
     * Ensure the indexes
     *
     * @return void
     * @throws DatabaseException
     *
     */
    public static function ensureIndexes(): void
    {
        $instance = new self();
        $instance->addIndex(['name' => 1]);
    }
}