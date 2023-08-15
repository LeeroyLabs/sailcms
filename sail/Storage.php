<?php

namespace SailCMS;

use League\Flysystem\FilesystemException;
use League\Flysystem\MountManager;
use League\MimeTypeDetection\FinfoMimeTypeDetector;
use SailCMS\Errors\StorageException;
use SailCMS\Internal\Filesystem;
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
            throw new StorageException('Could not store file ' . $filename . ': ' . $e->getMessage(), 500);
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
     * @throws StorageException
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
            throw new StorageException('Cannot delete file or directory ' . $filename . ': ' . $e->getMessage(), 500);
        }
    }

    /**
     *
     * Get mime-type for given file
     *
     * @param  string  $filename
     * @return string
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
     * @return void
     * @throws StorageException
     *
     */
    public function createDirectory(string $directoryName, string $permission = 'private'): void
    {
        try {
            $this->fs->createDirectory($this->buildFileString($directoryName), ['visibility' => $permission]);
        } catch (FilesystemException $e) {
            throw new StorageException('Cannot create directory ' . $directoryName . ': ' . $e->getMessage(), 500);
        }
    }

    /**
     *
     * Alias for delete
     *
     * @param  string  $directoryName
     * @return void
     * @throws StorageException
     *
     */
    public function deleteDirectory(string $directoryName): void
    {
        $this->delete($directoryName, true);
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
            return false;
        }
    }

    /**
     *
     * Move a file to another location
     *
     * @param  string  $originalLocation
     * @param  string  $newLocation
     * @param  string  $permission
     * @return bool
     *
     */
    public function move(string $originalLocation, string $newLocation, string $permission = 'private'): bool
    {
        $path1 = $this->buildFileString($originalLocation);
        $path2 = $this->buildFileString($newLocation);

        try {
            $this->fs->move($path1, $path2, ['visibility' => $permission]);
            return true;
        } catch (FilesystemException $e) {
            return false;
        }
    }

    /**
     *
     * Copy a file to another location
     *
     * @param  string  $originalLocation
     * @param  string  $newLocation
     * @param  string  $permission
     * @return bool
     *
     */
    public function copy(string $originalLocation, string $newLocation, string $permission = 'private'): bool
    {
        $path1 = $this->buildFileString($originalLocation);
        $path2 = $this->buildFileString($newLocation);

        try {
            $this->fs->copy($path1, $path2, ['visibility' => $permission]);
            return true;
        } catch (FilesystemException $e) {
            return false;
        }
    }

    /**
     *
     * Set permission for given file
     *
     * @param  string  $filename
     * @param  string  $permission
     * @return bool
     *
     */
    public function setPermissions(string $filename, string $permission): bool
    {
        try {
            $this->fs->setVisibility($this->buildFileString($filename), $permission);
            return true;
        } catch (FilesystemException $e) {
            return false;
        }
    }

    private function buildFileString(string $filename): string
    {
        return $this->disk . '://' . $filename;
    }
}