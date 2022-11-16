<?php

namespace SailCMS\Models;

use ImagickException;
use League\Flysystem\FilesystemException;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use SailCMS\Assets\Optimizer;
use SailCMS\Assets\Size;
use SailCMS\Assets\Transformer;
use SailCMS\Collection;
use SailCMS\Database\Model;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\FileException;
use SailCMS\Errors\PermissionException;
use SailCMS\Filesystem;
use SailCMS\Locale;
use SailCMS\Sail;
use SailCMS\Types\LocaleField;
use SailCMS\Types\Listing;
use SailCMS\Types\Pagination;
use SailCMS\Types\QueryOptions;

class Asset extends Model
{
    public string $filename;
    public string $site_id;
    public string $name;
    public LocaleField $title;
    public string $folder;
    public string $url;
    public int $filesize;
    public Size $size;
    public bool $is_image;
    public string $uploader_id;
    public bool $public;
    public int $created_at;
    public Collection $transforms;

    public function fields(bool $fetchAllFields = false): array
    {
        return [
            '_id',
            'filename',
            'title',
            'name',
            'url',
            'filesize',
            'is_image',
            'size',
            'uploader_id',
            'public',
            'created_at',
            'transforms',
            'folder'
        ];
    }

    public function init(): void
    {
        $this->setPermissionGroup('assets');
    }

    protected function processOnFetch(string $field, mixed $value): mixed
    {
        if ($field === 'size') {
            return new Size($value->width, $value->height);
        }

        if ($field === 'title') {
            return new LocaleField($value);
        }

        return $value;
    }

    /**
     *
     * Get an asset by its id
     *
     * @param  string|ObjectId  $id
     * @return Asset|null
     * @throws DatabaseException
     *
     */
    public static function getById(string|ObjectId $id): ?Asset
    {
        $instance = new static();
        return $instance->findById($id)->exec();
    }

    /**
     *
     * Get an asset by its id
     *
     * @param  string  $name
     * @return Asset|null
     * @throws DatabaseException
     *
     */
    public static function getByName(string $name): ?Asset
    {
        $instance = new static();
        return $instance->findOne(['name' => $name])->exec();
    }

    /**
     *
     * Get list of assets
     *
     * @param  int     $page
     * @param  int     $limit
     * @param  string  $folder
     * @param  string  $search
     * @param  string  $sort
     * @param  int     $direction
     * @return Listing
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function getList(int $page = 1, int $limit = 50, string $folder = 'root', string $search = '', string $sort = 'name', int $direction = Model::SORT_ASC): Listing
    {
        $this->hasPermissions(true);

        $offset = $page * $limit - $limit;
        $query = ['site_id' => Sail::siteId(), 'folder' => $folder];

        // Search by name
        if (!empty($search)) {
            $query['name'] = new Regex($search, 'gi');
        }

        // Options for pagination and sorting
        $options = QueryOptions::initWithPagination($offset, $limit);
        $options->sort = [$sort => $direction];

        $results = $this
            ->find($query, $options)
            ->populate('uploader_id', 'uploader', User::class)
            ->exec();

        $count = $this->count([]);
        $total = ceil($count / $limit);

        $pagination = new Pagination($page, $total, $count);
        return new Listing($pagination, new Collection($results));
    }

    /**
     *
     * Upload an asset, process it if it's an image
     *
     * @param  string                $data
     * @param  string                $filename
     * @param  string                $folder
     * @param  ObjectId|User|string  $uploader
     * @return string
     * @throws DatabaseException
     * @throws FileException
     * @throws FilesystemException
     * @throws ImagickException
     *
     */
    public function upload(string $data, string $filename, string $folder = 'root', ObjectId|User|string $uploader = ''): string
    {
        $fs = Filesystem::manager();

        $upload_id = substr(hash('sha256', uniqid(uniqid('', true), true)), 10, 8);

        // Options
        $adapter = setting('assets.adapter', 'local://');
        $maxSize = setting('assets.maxUploadSize', 5);
        $optimize = setting('assets.optimizeOnUpload', true);
        $transforms = setting('assets.onUploadTransforms', []);
        $quality = setting('assets.transformQuality', 92);

        // Size of image
        $sizeBytes = strlen(bin2hex($data));
        $sizeMB = ($sizeBytes / 1024) / 1024;

        if ($sizeMB > $maxSize) {
            throw new FileException("Asset is too big, maximum upload size is {$maxSize}mb", 0403);
        }

        $basePath = $adapter . '://';
        $path = $adapter . '://';

        // Local adapter is special
        if ($adapter === 'local') {
            $basePath = 'local://';
            $path = 'local://uploads/';
        }

        // All folders are Year/Month (of upload)
        $timePath = date('Y/m/');

        $info = explode('.', $filename);
        $ext = end($info);

        // Add the unique id to the asset now
        $the_name = basename($filename);
        $filename = str_replace(".{$ext}", "-{$upload_id}.{$ext}", $filename);

        // Check support formats for post-processing
        $processableImage = match ($ext) {
            'jpeg', 'jpg', 'png' => true,
            default => false,
        };

        $isImage = match ($ext) {
            'jpeg', 'jpg', 'png', 'webp', 'gif' => true,
            default => false
        };

        if ($processableImage) {
            // Optimize to webp format
            if ($optimize) {
                $data = Optimizer::process($data, $quality);
                $filename = str_replace($ext, 'webp', $filename);
                $the_name = str_replace($ext, 'webp', $the_name);
            }

            // Size of the file (width/height)
            $size = Transformer::getImageSizeFromSource($data);
        } else {
            // Not applicable
            $size = new Size(0, 0);
        }

        // Store asset
        $fs->write($path . $timePath . $filename, $data, ['visibility' => 'public']);

        // Determine user that uploaded it, if possible
        $uploader_id = '';

        if (is_string($uploader)) {
            $uploader_id = $uploader;
        } elseif (is_object($uploader) && get_class($uploader) === ObjectId::class) {
            $uploader_id = (string)$uploader;
        } elseif (is_object($uploader) && get_class($uploader) === User::class) {
            $uploader_id = (string)$uploader->_id;
        }

        $locales = Locale::getAvailableLocales();
        $title = [];

        foreach ($locales as $locale) {
            $title[$locale] = $the_name;
        }

        $titles = new LocaleField($title);

        // Create entry
        $id = $this->insert([
            'filename' => $path . $timePath . $filename,
            'name' => $the_name,
            'title' => $titles,
            'url' => $fs->publicUrl($basePath . $timePath . $filename),
            'is_image' => $isImage,
            'filesize' => $sizeBytes,
            'size' => $size,
            'uploader_id' => $uploader_id,
            'public' => ($uploader_id === ''),
            'transforms' => [],
            'created_at' => time(),
            'folder' => $folder
        ]);

        // Run all transforms on upload configured
        if ($isImage) {
            $image = $this->findById($id)->exec();

            foreach ($transforms->unwrap() as $name => $transform) {
                $transform = new Collection($transform);
                $image->transform($name, $transform->get('width', null), $transform->get('height', null), $transform->get('crop', ''));
            }
        }

        // Return the URL for the asset
        if ($adapter === 'local') {
            return env('site_url', 'http://localhost') . $fs->publicUrl($basePath . $timePath . $filename);
        }

        return $fs->publicUrl($basePath . $timePath . $filename);
    }

    /**
     *
     * Create a transform on an asset
     *
     * @param  string    $name
     * @param  int|null  $width
     * @param  int|null  $height
     * @param  string    $crop
     * @return string
     * @throws FilesystemException
     * @throws ImagickException
     * @throws DatabaseException
     *
     */
    public function transform(string $name, ?int $width = 100, ?int $height = null, string $crop = Transformer::CROP_CC): string
    {
        if ($width === null && $height === null) {
            throw new \RuntimeException('Cannot transform an without at least 1 value for width or height', 0400);
        }

        if ($this->is_image) {
            $fs = Filesystem::manager();
            $format = setting('assets.transformOutputFormat', 'webp');
            $info = explode('.', $this->filename);
            $ext = end($info);
            array_pop($info);

            $transformFilename = implode('.', $info) . '-' . $name . '.' . $format;

            $transformable = match ($ext) {
                'jpeg', 'jpg', 'png', 'webp' => true,
                default => false,
            };

            if ($transformable) {
                // Check to see if already processed
                $cache = null;

                $transforms = $this->transforms->find(function ($key, $value) use (&$cache, $name)
                {
                    if ($value->transform === $name) {
                        $cache = $value;
                    }
                });

                // Hit!
                if ($cache !== null) {
                    if (str_starts_with($transformFilename, 'local://')) {
                        return env('site_url', 'http://localhost') . $cache->url;
                    }

                    return $cache->url;
                }

                // Miss, generate it
                $transform = new Transformer($this->filename);

                if ($crop !== '') {
                    $transform->resizeAndCrop($width, $height, $crop)->save($transformFilename, $format);
                } else {
                    $transform->resize($width, $height)->save($transformFilename, $format);
                }

                // Get url of the transform (in case of local:// remove the uploads mention)
                $fileURL = $transformFilename;
                if (str_starts_with($transformFilename, 'local://')) {
                    $fileURL = str_replace('uploads/', '', $transformFilename);
                }

                $transformData = [
                    'transform' => $name,
                    'filename' => $transformFilename,
                    'name' => basename($transformFilename),
                    'url' => $fs->publicUrl($fileURL)
                ];

                $this->updateOne(['_id' => $this->_id], ['$push' => ['transforms' => $transformData]]);

                // For local only
                if (str_starts_with($transformFilename, 'local://')) {
                    return env('site_url', 'http://localhost') . $fs->publicUrl($fileURL);
                }

                return $fs->publicUrl($fileURL);
            }
        }

        // Cannot be transformed, return url
        return $this->url;
    }

    /**
     *
     * Transform an asset using it's id and settings
     *
     * @param  string|ObjectId  $id
     * @param  string           $name
     * @param  int|null         $width
     * @param  int|null         $height
     * @param  string           $crop
     * @return string
     * @throws DatabaseException
     * @throws ImagickException
     * @throws FilesystemException
     * @throws DatabaseException
     *
     */
    public static function transformById(string|ObjectId $id, string $name, ?int $width, ?int $height, string $crop = Transformer::CROP_CC): string
    {
        $asset = static::getById($id);

        if ($asset && $asset->is_image) {
            return $asset->transform($name, $width, $height, $crop);
        }

        return $asset->url ?? '';
    }

    /**
     *
     * Update asset's title in the requested locale
     *
     * @param  string  $locale
     * @param  string  $title
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function update(string $locale, string $title): bool
    {
        $this->hasPermissions();
        $this->updateOne(['_id' => $this->_id], ['$set' => ["title.{$locale}" => $title]]);
        return true;
    }

    /**
     *
     * Update an asset's title in the request locale by its id
     *
     * @param  ObjectId|string  $id
     * @param  string           $locale
     * @param  string           $title
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public static function updateById(ObjectId|string $id, string $locale, string $title): bool
    {
        $instance = new static();
        $asset = $instance->findById($id)->exec();

        if ($asset) {
            return $asset->update($locale, $title);
        }

        return false;
    }

    /**
     *
     * Delete an asset
     *
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function remove(): bool
    {
        $this->hasPermissions();
        $this->deleteById($this->_id);
        return true;
    }

    /**
     *
     * Delete an asset by id
     *
     * @param  ObjectId|string  $id
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public static function removeById(ObjectId|string $id): bool
    {
        $instance = new static();
        $asset = $instance->findById($id)->exec();

        if ($asset) {
            return $asset->remove();
        }

        return false;
    }
}