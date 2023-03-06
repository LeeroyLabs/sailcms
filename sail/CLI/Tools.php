<?php

namespace SailCMS\CLI;

use Exception;
use League\Flysystem\FilesystemException;
use SailCMS\Filesystem;
use SailCMS\Sail;

use function Termwind\render;

class Tools
{
    /**
     *
     * Install a composer package
     *
     * @param  string  $package
     * @return void
     *
     */
    public static function executeComposerInstall(string $package): void
    {
        $cmp = env('composer_location', '/usr/local/bin/composer');
        shell_exec('yes | ' . $cmp . ' -n require ' . $package . ' 2>/dev/null');
    }

    /**
     *
     * Execute composer's dump-autoload from php
     *
     * @return void
     *
     */
    public static function executeComposerRefresh(): void
    {
        exec(env('composer_location', '/usr/local/bin/composer') . ' dump-autoload -a');
    }

    /**
     *
     * Pre-check what is required to perform actions on directories and files
     *
     * @param  bool  $skipFolders
     * @return void
     * @throws FilesystemException
     *
     */
    public static function testFlight(bool $skipFolders = false): void
    {
        if (env('composer_location', '') === '') {
            static::outputError("Composer location not found in environment file, please make sure it's set", true);
        }

        if ($skipFolders) {
            return;
        }

        $fs = Filesystem::manager();

        if ($fs->visibility('root://modules') !== 'public') {
            static::outputError("SailCMS is not allowed to write in the modules directory", true);
        }

        if ($fs->visibility('local://') !== 'public') {
            $path = '/storage/fs/' . Sail::siteId();
            static::outputError("SailCMS is not allowed to write in the '{$path}' directory", true);
        }

        if ($fs->visibility('cache://') !== 'public') {
            $path = '/storage/cache/' . Sail::siteId();
            static::outputError("SailCMS is not allowed to write in the '{$path}' directory", true);
        }
    }

    /**
     *
     * Output as style error message to the console and kill off the command.
     *
     * @param  string  $message
     * @param  bool    $die
     * @param  bool    $pad
     * @return void
     *
     */
    public static function outputError(string $message, bool $die = false, bool $pad = false): void
    {
        $html = <<<'HTML'
            <div class="[pad]">
                <div class="px-1 bg-red-500 text-black w-12 text-center">FATAL</div>
                <em class="ml-1">
                  [message]
                </em>
            </div>
        HTML;

        $padded = ($pad) ? 'py-1' : '';

        render(str_replace([
            '[message]',
            '[pad]',
            '[b]',
            '[/b]'
        ], [
            $message,
            $padded,
            '<span class="font-bold">',
            '</span>'
        ], $html));

        if ($die) {
            die();
        }
    }

    /**
     *
     * Output an info line with the given title (in colors background), message and custom color for background
     * and a width to respect (for title section)
     *
     * @param  string  $title
     * @param  string  $message
     * @param  string  $color
     * @param  string  $width
     * @return void
     *
     */
    public static function outputInfo(string $title, string $message, string $color = 'bg-blue-600', string $width = 'w-12'): void
    {
        $html = <<<'HTML'
            <div>
                <div class="[color] text-black [width] text-center">[title]</div>
                <em class="ml-1">
                  [message]
                </em>
            </div>
        HTML;

        render(str_replace([
                '[title]',
                '[message]',
                '[color]',
                '[b]',
                '[/b]',
                '[width]'
            ], [
                strtoupper($title),
                $message,
                $color,
                '<span class="font-bold">',
                '</span>',
                $width
            ], $html)
        );
    }

    /**
     *
     * Show the installation message before installing
     *
     * @param  string  $title
     * @return void
     *
     */
    public static function showTitle(string $title): void
    {
        $html = '<div class="bg-blue-600 text-white px-2 py-1 my-2 w-full text-center">[title]</div>';
        render(str_replace('[title]', $title, $html));
    }
}