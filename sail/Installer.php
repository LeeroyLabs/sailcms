<?php

namespace SailCMS;

use \League\CLImate\CLImate;

class Installer
{
    public static function run(string $path)
    {
        $folders = [
            'public', 'public/default', 'sites', 'sites/default', 'web', 'storage',
            'storage/uploads', 'storage/uploads/default', 'config', 'config/default',
            'modules'
        ];

        $files = [
            'web/index.php' => 'index.php', 'config/default/general.php' => 'config/general.php',
            'config/apps.env.php' => 'env.php', 'modules/.gitkeep' => ''
        ];

        $climate = new CLImate();
        $climate->lightBlue('Installing SailCMS v3.0.0');

        foreach ($folders as $folder) {
            if (!mkdir($concurrentDirectory = $path . '/' . $folder) && !is_dir($concurrentDirectory)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
            }
        }

        $workPath = dirname(__DIR__) . '/install/';

        foreach ($files as $key => $file) {
            if (str_contains($key, 'gitkeep')) {
                touch($path . '/' . $key);
            } else {
                file_put_contents($path . '/' . $key, file_get_contents($workPath . $file));
            }
        }
    }
}