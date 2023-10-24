<?php

namespace SailCMS\Internal;

use League\Flysystem\Filesystem as FS;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\MountManager;
use SailCMS\Sail;
use SailCMS\Text;

final class Filesystem
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
        if (empty(self::$adapters[$identifier])) {
            self::$adapters[$identifier] = new FS($adapter);
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
        $wd = Sail::getWorkingDirectory();
        $host = '/assets';

        if ($forcedPath === '') {
            $path = $wd;
        }
        
        self::$adapters['local'] = new FS(new LocalFilesystemAdapter($path . '/'), ['public_url' => $host]);
        self::$adapters['app'] = new FS(new LocalFilesystemAdapter($wd . '/storage/fs/app'), ['public_url' => '/fs']);
        self::$adapters['vault'] = new FS(new LocalFilesystemAdapter($wd . '/storage/fs/vault/'));
        self::$adapters['cache'] = new FS(new LocalFilesystemAdapter($wd . '/storage/cache/'));
        self::$adapters['debug'] = new FS(new LocalFilesystemAdapter($wd . '/storage/debug/'));
        self::$adapters['logs'] = new FS(new LocalFilesystemAdapter($wd . '/storage/fs/logs/'));
        self::$adapters['root'] = new FS(new LocalFilesystemAdapter($wd . '/'));
        self::$adapters['install'] = new FS(new LocalFilesystemAdapter(dirname(__DIR__, 2) . '/install/'));
        self::$adapters['cms'] = new FS(new LocalFilesystemAdapter(dirname(__DIR__, 2) . '/cms/'));
        self::$adapters['cms_root'] = new FS(new LocalFilesystemAdapter(dirname(__DIR__, 2) . '/'));
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
     * Get file extension from filepath or filename
     * Logical place to find this method
     *
     * @param  string  $file
     * @return string
     *
     */
    public static function getExtension(string $file): string
    {
        return Text::from($file)->extension();
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
        self::$manager = new MountManager(self::$adapters);
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
        if (isset(self::$manager)) {
            return self::$manager;
        }

        self::mountCore();
        self::init();
        return self::$manager;
    }
}