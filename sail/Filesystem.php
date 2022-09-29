<?php

namespace SailCMS;

use \League\Flysystem\FilesystemAdapter;
use \League\Flysystem\Local\LocalFilesystemAdapter;
use \League\Flysystem\MountManager;
use \League\Flysystem\Filesystem as FS;

class Filesystem
{
    private static array $adapters = [];
    private static MountManager $manager;

    /**
     *
     * Add a custom filesystem adapter
     *
     * @param string $identifier
     * @param FilesystemAdapter $adapter
     * @return void
     *
     */
    public static function mount(string $identifier, FilesystemAdapter $adapter): void
    {
        // Do not load twice
        if (empty(static::$adapters[$identifier])) {
            static::$adapters[$identifier] = new FS($adapter);
        }
    }

    /**
     *
     * Add the basic filesystem adapter (local)
     *
     * @return void
     *
     */
    public static function mountCore(string $forcedPath = ''): void
    {
        $path = $forcedPath;

        if ($forcedPath === '') {
            $path = Sail::getFSDirectory();
        }

        static::$adapters['local'] = new FS(new LocalFilesystemAdapter($path . '/'));
    }

    /**
     *
     * Initialize the fs manager
     *
     * @return void
     *
     */
    public static function init(): void
    {
        static::$manager = new MountManager(static::$adapters);
    }

    /**
     *
     * Get access to the manager
     *
     * @return MountManager
     *
     */
    public static function manager(): MountManager
    {
        return static::$manager;
    }
}