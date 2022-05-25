<?php

namespace SailCMS;

class Installer
{
    public static function run(string $path)
    {
        $folders = [
            'public', 'public/default', 'sites', 'sites/default', 'web', 'storage',
            'storage/uploads', 'storage/uploads/default', 'config'
        ];

        $files = [
            'web/index.php', 'config/general.php'
        ];

        foreach ($folders as $folder) {
            if (!mkdir($concurrentDirectory = $path . '/' . $folder) && !is_dir($concurrentDirectory)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
            }
        }

        foreach ($files as $file) {
            touch($path . '/' . $file);
        }
    }
}