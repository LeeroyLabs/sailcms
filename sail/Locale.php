<?php

namespace SailCMS;

use League\Flysystem\FilesystemException;
use SailCMS\Internal\Filesystem;
use Symfony\Component\Yaml\Yaml;

class Locale
{
    public static string $current;
    private static string $default;
    public static array $strings = [];

    private static array $availableLocales = [];

    /**
     *
     * Set the current locale
     *
     * @param  string  $locale
     * @param  bool    $skipReload
     * @throws FilesystemException
     *
     */
    public static function setCurrent(string $locale, bool $skipReload = false): void
    {
        self::$current = $locale;

        if (!$skipReload) {
            self::loadAll();
        }
    }

    /**
     *
     * Set default language for the cms
     *
     * @param  string  $locale
     * @return void
     *
     */
    public static function setDefault(string $locale): void
    {
        self::$default = $locale;
    }

    /**
     *
     * Get current Locale value
     *
     * @return string
     *
     */
    public static function current(): string
    {
        return self::$current;
    }

    /**
     *
     * Get the default locale
     *
     * @return string
     *
     */
    public static function default(): string
    {
        return self::$default;
    }

    /**
     *
     * Replace the current Locale for the requested locale
     *
     * @param  string  $locale
     * @return void
     * @throws FilesystemException
     *
     */
    public function reset(string $locale): void
    {
        self::setCurrent($locale);
    }

    /**
     *
     * Find a translation using a dot notation path.
     *
     * @param  string  $path
     * @return string
     *
     */
    public static function translate(string $path): string
    {
        $parts = explode('.', $path);
        $val = null;

        foreach ($parts as $num => $part) {
            if (empty($val)) {
                $val = self::$strings[$part];
            } elseif (!empty($val[$part])) {
                $val = $val[$part];
            } else {
                $val = $path;
            }
        }

        return $val;
    }

    /**
     *
     * Alias for translate (to keep it short)
     *
     * @param  string  $path
     * @return string
     *
     */
    public static function t(string $path): string
    {
        return self::translate($path);
    }

    /**
     *
     * Instance version of the shortcut
     *
     * @param  string  $path
     * @return string
     *
     */
    public function _(string $path): string
    {
        return self::translate($path);
    }

    /**
     *
     * Instance version of the shortcut except it echoes out
     *
     * @param  string  $path
     * @return void
     *
     */
    public function _e(string $path): void
    {
        echo self::translate($path);
    }

    /**
     *
     * Set available locales for site
     *
     * @param  array  $locales
     * @return void
     *
     */
    public static function setAvailableLocales(array $locales): void
    {
        self::$availableLocales = $locales;
    }

    /**
     *
     * Get available locales for site
     *
     * @return Collection
     *
     */
    public static function getAvailableLocales(): Collection
    {
        return new Collection(self::$availableLocales);
    }

    /**
     *
     * @throws FilesystemException
     *
     */
    private static function loadAll(): void
    {
        $fs = Filesystem::manager();
        $filepath = 'root://locales/' . self::$current . '.yaml';

        // Create missing document
        if (!$fs->directoryExists(dirname($filepath))) {
            $fs->createDirectory(dirname($filepath));
        }

        // Create missing file
        if (!$fs->fileExists($filepath)) {
            $fs->write($filepath, '');
        }

        // Load global locale file
        $file = $fs->read($filepath);
        $yaml = Yaml::parse($file);

        if (!empty($yaml)) {
            self::$strings = $yaml;
        }

        // General CMS Locales
        self::loadFromDirectories(['cms_root://sail']);

        // General Application Level
        self::loadFromDirectories(['root://']);

        // Load all container locale files
        self::loadFromDirectories($fs->listContents('root://containers', false)->toArray());
    }

    /**
     *
     * Loop through the given directories for locale files
     *
     * @param  array  $directories
     * @return void
     * @throws FilesystemException
     *
     */
    private static function loadFromDirectories(array $directories): void
    {
        $fs = Filesystem::manager();

        foreach ($directories as $directory) {
            if (is_object($directory) && $directory->isDir()) {
                $path = $directory->path() . '/locales';

                if ($fs->directoryExists($path) && $fs->fileExists($path . '/' . self::$current . '.yaml')) {
                    $file = $fs->read($path . '/' . self::$current . '.yaml');
                    $yaml = Yaml::parse($file);

                    if (!empty($yaml)) {
                        self::$strings = [...$yaml];
                    }
                }
            } elseif (is_string($directory)) {
                $path = $directory . '/locales';

                if ($fs->directoryExists($path) && $fs->fileExists($path . '/' . self::$current . '.yaml')) {
                    $file = $fs->read($path . '/' . self::$current . '.yaml');
                    $yaml = Yaml::parse($file);

                    if (!empty($yaml)) {
                        self::$strings = [...$yaml];
                    }
                }
            }
        }
    }
}