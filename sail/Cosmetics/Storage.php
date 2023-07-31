<?php

namespace SailCMS\Cosmetics;

use League\Flysystem\FilesystemException;
use League\Flysystem\MountManager;
use SailCMS\Errors\StorageException;
use SailCMS\Filesystem;
use SailCMS\Types\StorageType;

class Storage
{
    private MountManager $fs;
    private string $disk = 'local';
    private string $permission = 'private';
    private string $filename = '';

    public function __construct()
    {
        $this->fs = Filesystem::manager();
    }

    /**
     *
     *
     * Storage::on('s3')->url('xxxx.jpg');
     * Storage::on('locale')->setPermissions('xxxx')->store('xxxx', 'xxxx');
     *
     * Storage::on('s3')->store('xxx', 'xxxx')->url();
     *
     */

    /**
     *
     * Set disk where to send or get the file from
     *
     * @param  string  $disk
     * @return self
     *
     */
    public static function on(string $disk = StorageType::TYPE_DEFAULT): self
    {
        $instance = new self();
        $instance->disk = $disk;
        return $instance;
    }

    /**
     *
     * Set the permission of the file
     *
     * @param  string  $permission
     * @return $this
     *
     */
    public function permissions(string $permission): self
    {
        $this->permission = $permission;
        return $this;
    }

    /**
     *
     * Store file
     *
     * @param  string  $filename
     * @param  mixed   $data
     * @return self
     * @throws StorageException
     *
     */
    public function store(string $filename, mixed $data): self
    {
        $permission = ['visibility' => $this->permission];
        $path = $this->buildFileString($filename);
        $this->filename = $filename;

        try {
            if (is_resource($data)) {
                $this->fs->writeStream($path, $data, $permission);
            } else {
                $this->fs->write($path, $data, $permission);
            }

            return $this;
        } catch (FilesystemException $e) {
            throw new StorageException('Could not store file: ' . $e->getMessage(), '500');
        }
    }

    /**
     *
     * Get public URL of the file
     *
     * @param  string  $filename
     * @return string
     *
     */
    public function url(string $filename = ''): string
    {
        if (empty($filename) && !empty($this->filename)) {
            // Assume file that was just uploaded
            return $this->fs->publicUrl($this->buildFileString($this->filename));
        }

        return $this->fs->publicUrl($this->buildFileString($filename));
    }

    // read,
    // delete,
    // getFilesIn,
    // mimetype,
    // exists,
    // create directory,
    // delete directory,
    // setPermissions

    private function buildFileString(string $filename): string
    {
        return $this->disk . '://' . $filename;
    }
}