<?php

namespace SailCMS;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use SailCMS\Errors\DatabaseException;
use SailCMS\Models\Monitoring;
use SailCMS\Types\Monitoring\PHPVersionInformation;

class SystemMonitor
{
    /**
     *
     * Take a sample of system resources for monitoring
     *
     * @param  bool  $testPHP
     * @throws DatabaseException
     * @throws \JsonException
     *
     */
    public static function sample(bool $testPHP = false): void
    {
        // Let's use our python script to fetch everything using psutil
        exec('python3 ' . dirname(__DIR__) . '/scripts/system.py', $result);

        $ram = [
            'total' => (int)$result[0],
            'available' => (int)$result[1],
            'used' => (int)$result[2],
            'percent' => (float)$result[3]
        ];

        $disk = [
            'total' => (int)$result[4],
            'available' => (int)$result[5],
            'used' => (int)$result[6],
            'percent' => (float)$result[7]
        ];

        $cpu = [
            'percent' => (float)$result[8],
            'cores' => (int)$result[9]
        ];

        $boot = (int)$result[10];
        $opts = ['join' => ' ', 'parts' => 3, 'syntax' => CarbonInterface::DIFF_ABSOLUTE];
        $diff = Carbon::createFromTimestamp($boot, 'America/New_York')->diffForHumans(Carbon::now('America/New_York'), $opts);

        $boot = [
            'boot_time' => $boot,
            'uptime' => $diff
        ];

        $php = null;

        if ($testPHP) {
            $php = self::checkPHPVersion();
        }
        $sample = new Monitoring();
        $warning = false;

        // If any of the 3 is 90 or above, flag as a warning (configuration decides how many before email is sent)
        if ($ram['percent'] >= 90 || $disk['percent'] >= 90 || $cpu['percent'] >= 90) {
            $warning = true;
        }

        $sample->ram = $ram;
        $sample->disk = $disk;
        $sample->cpu = $cpu;
        $sample->boot = $boot;
        $sample->php = $php;
        $sample->warning = $warning;
        $sample->timestamp = Carbon::now('America/New_York')->getTimestamp();
        $sample->php_tested = $testPHP;
        $sample->identifier = gethostname();
        $sample->save();

        // Notification system will check if the last X records have warnings
        try {
            Monitoring::notify();
        } catch (\Exception $e) {
            // nothing to do! something is not right with the system
        }
    }

    /**
     *
     * Check current installed version of php against the last 3
     * Check if installed is latest, check if installed is secure and if it's still supported
     *
     * @return PHPVersionInformation
     * @throws \JsonException
     *
     */
    public static function checkPHPVersion(): PHPVersionInformation
    {
        $data = json_decode(
            file_get_contents('https://php.watch/api/v1/versions/secure'),
            false,
            512,
            JSON_THROW_ON_ERROR
        );

        $safe = false;
        $latest = false;
        $current = '';
        $supported = false;
        $versionData = new Collection((array)$data->data);
        $installed = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        $versionData->each(function ($k, $el) use (&$safe, &$latest, &$current, &$supported, $installed)
        {
            // Current version installed?
            if ($el->isLatestVersion) {
                $current = $el->name;
                if ($installed === $el->name) {
                    $latest = true;
                }
            }

            if (str_contains($el->statusLabel, 'Supported')) {
                $supported = true;
            }

            // Current installed version is safe?
            if ($el->name === $installed) {
                if ($el->isSecureVersion) {
                    $safe = true;
                }
            }

            return null;
        });

        return new PHPVersionInformation($installed, $current, $latest, $safe, $supported);
    }
}