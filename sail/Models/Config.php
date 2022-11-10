<?php

namespace SailCMS\Models;

use SailCMS\Database\BaseModel;
use SailCMS\Errors\DatabaseException;

class Config extends BaseModel
{
    public string $name;
    public array|object $config;

    public function fields(bool $fetchAllFields = false): array
    {
        return ['name', 'config'];
    }

    /**
     *
     * Get a config by name
     *
     * @param  string  $name
     * @return static|null
     * @throws DatabaseException
     *
     */
    public static function getByName(string $name): ?static
    {
        $instance = new static();
        return $instance->findOne(['name' => $name])->exec();
    }

    /**
     *
     * Store a setting to the config name
     *
     * @param  string        $name
     * @param  array|object  $config
     * @return void
     * @throws DatabaseException
     *
     */
    public static function setByName(string $name, array|object $config): void
    {
        $instance = new static();
        $record = static::getByName($name);

        if (!$record) {
            $instance->insert([
                'name' => $name,
                'config' => $config
            ]);
            return;
        }

        $instance->updateOne(['name' => $name], ['$set' => $config]);
    }
}