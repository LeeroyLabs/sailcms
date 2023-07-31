<?php

namespace SailCMS\Cosmetics;

use League\Flysystem\FilesystemException;
use League\Flysystem\MountManager;
use League\MimeTypeDetection\FinfoMimeTypeDetector;
use SailCMS\Errors\StorageException;
use SailCMS\Filesystem;
use SailCMS\Types\FileSize;
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

    /**
     *
     * Read a file
     *
     * @param  string  $filename
     * @return mixed
     * @throws FilesystemException
     *
     */
    public function read(string $filename = ''): mixed
    {
        if (empty($filename) && !empty($this->filename)) {
            // Assume file that was just uploaded
            return $this->fs->read($this->buildFileString($this->filename));
        }

        return $this->fs->read($this->buildFileString($filename));
    }

    /**
     *
     * Delete file or directory
     *
     * @param  string  $filename
     * @param  bool    $isDirectory
     * @return bool
     *
     */
    public function delete(string $filename, bool $isDirectory = false): bool
    {
        try {
            if ($isDirectory) {
                $this->fs->deleteDirectory($this->buildFileString($filename));
            } else {
                $this->fs->delete($this->buildFileString($filename));
            }

            return true;
        } catch (FilesystemException $e) {
            return false;
        }
    }

    /**
     *
     * Get mime-type for given file
     *
     * @param  string  $filename
     * @return string
     * @throws FilesystemException
     *
     */
    public function mimetype(string $filename = ''): string
    {
        try {
            return (new FinfoMimeTypeDetector())->detectMimeType($this->buildFileString($filename), 'string contents');
        } catch (FilesystemException $e) {
            return 'unknown';
        }
    }

    /**
     *
     * Get filesize information for file
     *
     * @param  string  $filename
     * @return FileSize
     * @throws FilesystemException
     *
     */
    public function size(string $filename = ''): FileSize
    {
        return new FileSize($this->fs->fileSize($this->buildFileString($filename)));
    }

    /**
     *
     * Create a directory
     *
     * @param  string  $directoryName
     * @param  string  $permission
     * @return bool
     *
     */
    public function createDirectory(string $directoryName, string $permission = 'private'): bool
    {
        try {
            $this->fs->createDirectory($this->buildFileString($directoryName), ['visibility' => $permission]);
            return true;
        } catch (FilesystemException $e) {
            return false;
        }
    }

    /**
     *
     * Alias for delete
     *
     * @param  string  $directoryName
     * @return bool
     *
     */
    public function deleteDirectory(string $directoryName): bool
    {
        return $this->delete($directoryName, true);
    }

    /**
     *
     * Check if file or directory exists
     *
     * @param  string  $name
     * @param  bool    $isDirectory
     * @return bool
     *
     */
    public function exists(string $name, bool $isDirectory = false): bool
    {
        try {
            if ($isDirectory) {
                $this->fs->directoryExists($this->buildFileString($name));
                return true;
            }

            $this->fs->fileExists($this->buildFileString($name));
            return true;
        } catch (FilesystemException $e) {
        }
    }

    // getFilesIn,
    // setPermissions
    // move
    // copy

    private function buildFileString(string $filename): string
    {
        return $this->disk . '://' . $filename;
    }
}