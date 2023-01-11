<?php

namespace SailCMS\Models;

use JsonException;
use League\Flysystem\FilesystemException;
use SailCMS\Cache;
use SailCMS\Database\Model;
use SailCMS\Errors\DatabaseException;
use SailCMS\Security;
use SodiumException;

/**
 *
 * @property string       $name
 * @property array|object $config
 * @property bool         $flag
 *
 */
class Config extends Model
{
    protected string $collection = 'configs';

    /**
     *
     * Get a config by name
     *
     * @param  string  $name
     * @return static|null
     * @throws DatabaseException
     * @throws JsonException
     * @throws FilesystemException
     * @throws SodiumException
     *
     */
    public static function getByName(string $name): ?static
    {
        $item = self::query()->findOne(['name' => $name])->exec($name, Cache::TTL_MONTH);

        if ($item && $item->flag) {
            $item->config = json_decode(Security::decrypt($item->config), false, 512, JSON_THROW_ON_ERROR);
        }

        return $item;
    }

    /**
     *
     * Store a setting to the config name with optional encryption
     *
     * @param  string        $name
     * @param  array|object  $config
     * @param  bool          $encrypt
     * @return void
     * @throws DatabaseException
     * @throws FilesystemException
     * @throws JsonException
     * @throws SodiumException
     *
     */
    public static function setByName(string $name, array|object $config, bool $encrypt = false): void
    {
        $instance = new static();
        $record = static::getByName($name);

        if (!$record) {
            $data = $config;

            if ($encrypt) {
                $data = Security::encrypt(json_encode($config, JSON_THROW_ON_ERROR));
            }

            $instance->insert([
                'name' => $name,
                'config' => $data,
                'flag' => $encrypt
            ]);
            return;
        }

        $instance->updateOne(['name' => $name], ['$set' => ['config' => $config]]);
    }
}