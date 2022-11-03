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
     * @param  string             $identifier
     * @param  FilesystemAdapter  $adapter
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
     * @param  string  $forcedPath
     * @return void
     *
     */
    public static function mountCore(string $forcedPath = ''): void
    {
        $path = $forcedPath;

        if ($forcedPath === '') {
            $path = Sail::getFSDirectory();
        }

        $wd = Sail::getWorkingDirectory();
        $host = '/assets';

        static::$adapters['local'] = new FS(new LocalFilesystemAdapter($path . '/'), ['public_url' => $host]);
        static::$adapters['cache'] = new FS(new LocalFilesystemAdapter($wd . '/storage/cache/'));
        static::$adapters['logs'] = new FS(new LocalFilesystemAdapter($wd . '/storage/fs/logs/'));
        static::$adapters['root'] = new FS(new LocalFilesystemAdapter($wd . '/'));
        static::$adapters['install'] = new FS(new LocalFilesystemAdapter(dirname(__DIR__) . '/install/'));
        static::$adapters['cms'] = new FS(new LocalFilesystemAdapter(dirname(__DIR__) . '/cms/'));
        static::$adapters['cms_root'] = new FS(new LocalFilesystemAdapter(dirname(__DIR__) . '/'));
    }

    /**
     *
     * Get the logs folder path
     *
     * @return string
     *
     */
    public static function getLogsPath(): string
    {
        return Sail::getWorkingDirectory() . '/storage/fs/logs';
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