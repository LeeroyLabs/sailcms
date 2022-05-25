<?php

namespace SailCMS;

use \Composer\Installer\PackageEvent;

class Installer
{
    public static function runInstall(PackageEvent $event)
    {
        $package = $event->getOperation()->getPackage();
        $installationManager = $event->getComposer()->getInstallationManager();

        $originDir = $installationManager->getInstallPath($package);

        if (file_exists($originDir) && is_dir($originDir)) {
            mkdir("{$originDir}/sites");
            mkdir("{$originDir}/sites/default");
            mkdir("{$originDir}/public");
            mkdir("{$originDir}/public/default");

            copy(dirname(__DIR__) . '/cli', "{$originDir}/cli");
        }
    }
}