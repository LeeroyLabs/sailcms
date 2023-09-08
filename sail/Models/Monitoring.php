<?php

namespace SailCMS\Models;

use SailCMS\CLI\Monitor;
use SailCMS\Collection;
use SailCMS\Database\Model;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EmailException;
use SailCMS\Errors\FileException;
use SailCMS\Errors\PermissionException;
use SailCMS\Mail;
use SailCMS\SystemMonitor;
use SailCMS\Types\Monitoring\BootSample;
use SailCMS\Types\Monitoring\CpuSample;
use SailCMS\Types\Monitoring\DiskSample;
use SailCMS\Types\Monitoring\PHPVersionInformation;
use SailCMS\Types\Monitoring\RAMSample;
use Twig\Error\LoaderError;

/**
 *
 * @property RAMSample             $ram
 * @property DiskSample            $disk
 * @property CpuSample             $cpu
 * @property BootSample            $boot
 * @property PHPVersionInformation $php
 * @property bool                  $warning
 * @property int                   $timestamp
 * @property bool                  $php_tested
 * @property string                $identifier
 *
 */
class Monitoring extends Model
{
    protected string $collection = 'monitoring_samples';
    protected array $casting = [
        'ram' => RAMSample::class,
        'disk' => DiskSample::class,
        'cpu' => CpuSample::class,
        'boot' => BootSample::class,
        'php' => PHPVersionInformation::class
    ];

    protected string $permissionGroup = 'admin';

    /**
     *
     * Get sample data for the given start/end period
     *
     * @param  int  $start
     * @param  int  $end
     * @return Collection
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public static function getSampleBySize(int $start, int $end): Collection
    {
        $instance = new self();
        $instance->hasPermissions(true);

        return new Collection(
            self::query()->find(['timestamp' => ['$gte' => $start, '$lte' => $end]])->sort(['timestamp' => 1])->exec()
        );
    }

    /**
     *
     * Get a live sample
     *
     * @return array|Monitoring
     * @throws DatabaseException
     * @throws \JsonException
     * @throws ACLException
     * @throws PermissionException
     *
     */
    public static function getLiveSample(): array|Monitoring
    {
        $instance = new self();
        $instance->hasPermissions(true);

        SystemMonitor::sample(true, false);
        return self::query()->findOne([])->sort(['timestamp' => -1])->exec();
    }

    /**
     *
     * Notifier for issues with monitoring
     *
     * @return void
     * @throws DatabaseException
     * @throws EmailException
     * @throws FileException
     * @throws LoaderError
     *
     */
    public static function notify(): void
    {
        $sampleSize = setting('monitoring.problematic_sample_count_notify', 5);
        $samples = self::query()->find([])->sort(['timestamp' => -1])->limit($sampleSize)->exec();

        // We do not have enough to say a problem is ongoing
        if (count($samples) < $sampleSize) {
            return;
        }

        $warnings = 0;

        foreach ($samples as $sample) {
            if ($sample->warning) {
                $warnings++;
            }
        }

        if ($warnings >= $sampleSize) {
            $email = new Mail();
            $target = setting('monitoring.warning_email_address', '');

            // No warning
            if ($target === '') {
                return;
            }

            $context = ['url' => env('SITE_URL', 'http://localhost'), 'trigger' => setting('adminTrigger')];

            if (is_array($target)) {
                foreach ($target as $emailAddr) {
                    $email->to($emailAddr)->useEmail(
                        2,
                        'monitoring',
                        'en',
                        $context
                    )->send();
                }
            } else {
                $email->to($target)->useEmail(
                    2,
                    'monitoring',
                    'en',
                    $context
                )->send();
            }
        }
    }
}