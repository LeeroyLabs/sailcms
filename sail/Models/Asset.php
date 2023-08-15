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
use SailCMS\Internal\Filesystem;
use SailCMS\Locale;
use SailCMS\Text;
use SailCMS\Types\Listing;
use SailCMS\Types\LocaleField;
use SailCMS\Types\Pagination;
use SailCMS\Types\QueryOptions;

/**
 *
 * @property string      $filename
 * @property string      $site_id
 * @property string      $name
 * @property LocaleField $title
 * @property string      $folder
 * @property string      $url
 * @property int         $filesize
 * @property Size        $size
 * @property bool        $is_image
 * @property string      $uploader_id
 * @property bool        $public
 * @property int         $created_at
 * @property Collection  $transforms
 *
 */
class Asset extends Model
{
    protected string $collection = 'assets';
    protected string $permissionGroup = 'asset';
    protected array $casting = [
        'title' => LocaleField::class,
        'size' => Size::class,
        'transforms' => Collection::class
    ];

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
        return self::query()->findById($id)->exec((string)$id);
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
        return $instance->findOne(['name' => $name])->exec($name);
    }

    /**
     *
     * Get a transform for given image, if the transform does not exist, the full size image is returned
     *
     * @param  string|ObjectId  $id
     * @param  string           $transformName
     * @return string
     * @throws DatabaseException
     *
     */
    public static function getTransformURL(string|ObjectId $id, string $transformName): string
    {
        $asset = self::getById($id);

        if ($asset) {
            $url = $asset->url;

            foreach ($asset->transforms->unwrap() as $transform) {
                if ($transform->transform === $transformName) {
                    $url = $transform->url;
                }
            }

            return $url;
        }

        return '';
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
     * @param  string  $siteId
     * @return Listing
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function getList(int $page = 1, int $limit = 50, string $folder = 'root', string $search = '', string $sort = 'name', int $direction = Model::SORT_ASC, string $siteId = 'default'): Listing
    {
        $this->hasPermissions(true);

        $offset = $page * $limit - $limit;
        $query = ['site_id' => $siteId, 'folder' => $folder];

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
            ->exec("list_{$page}_{$limit}");

        $count = $this->count($query);
        $total = ceil($count / $limit);

        $pagination = new Pagination($page, (int)$total, $count);
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
     * @param  string                $siteId
     * @return self
     * @throws DatabaseException
     * @throws FileException
     * @throws FilesystemException
     * @throws ImagickException
     *
     */
    public function upload(string $data, string $filename, string $folder = 'root', ObjectId|User|string $uploader = '', string $siteId = 'default'): self
    {
        $fs = Filesystem::manager();

        $upload_id = substr(hash('sha256', uniqid(uniqid('', true), true)), 10, 8);

        // Options
        $adapter = setting('assets.adapter', 'local');
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
            $path = 'local://storage/fs/uploads/';
        }

        // All folders are Year/Month (of upload)
        $timePath = date('Y/m/');

        $info = explode('.', $filename);
        $ext = end($info);

        // Add the unique id to the asset now
        $the_name = Text::from($filename)->basename()->slug()->value();
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
            'uploader_id' => ($uploader_id !== '') ? $uploader_id : null,
            'public' => ($uploader_id === ''),
            'transforms' => [],
            'created_at' => time(),
            'folder' => $folder,
            'site_id' => $siteId
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
        return self::query()->findById($id)->populate('uploader_id', 'uploader', User::class)->exec();
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

                $this->transforms->each(function ($key, $value) use (&$cache, $name)
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
     * Create a custom transform using a cropping tool
     *
     * @param  string|ObjectId  $id
     * @param  string           $name
     * @param  string           $data
     * @return string
     * @throws DatabaseException
     * @throws FilesystemException
     * @throws ImagickException
     *
     */
    public static function transformCustom(string|ObjectId $id, string $name, string $data): string
    {
        $asset = static::getById($id);

        if ($asset && $asset->is_image) {
            $fs = Filesystem::manager();

            $upload_id = substr(hash('sha256', uniqid(uniqid('', true), true)), 10, 8);

            // Options
            $quality = setting('assets.transformQuality', 92);

            $info = explode('.', $asset->filename);
            $ext = end($info);

            // Transform base64 to actual image binary (forced optimization on custom crops)
            $data = Optimizer::process($data, $quality);

            // Add the unique id to the asset now
            $filename = str_replace(".{$ext}", "-{$upload_id}-{$name}.webp", $asset->filename);

            // Store asset
            $fs->write($filename, $data, ['visibility' => 'public']);

            $transforms = $asset->transforms->unwrap();

            // Remove existing crop if exists
            foreach ($transforms as $num => $transform) {
                if ($transform->transform === $name) {
                    unset($transforms[$num]);
                }
            }

            // Reset indexes
            $transforms = array_values($transforms);

            // Create new
            $transforms[] = [
                'transform' => $name,
                'filename' => $filename,
                'name' => basename($filename),
                'url' => $fs->publicUrl($filename)
            ];

            $asset->transforms = $transforms;
            $asset->save();

            return $fs->publicUrl($filename);
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
        self::query()->hasPermissions();
        $asset = self::query()->findById($id)->exec((string)$id);

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
        self::query()->hasPermissions();
        $asset = self::query()->findById($id)->exec((string)$id);

        if ($asset) {
            return $asset->remove();
        }

        return false;
    }

    /**
     *
     * Remove all given ids from the database
     *
     * @param  Collection|array  $assets
     * @return bool
     *
     */
    public function removeAll(Collection|array $assets): bool
    {
        try {
            $this->hasPermissions();
            $ids = $this->ensureObjectIds($assets);
            $this->deleteMany(['_id' => ['$in' => $ids->unwrap()]]);
            return true;
        } catch (ACLException|PermissionException|DatabaseException $e) {
            return false;
        }
    }

    /**
     *
     * Add files to given folder
     *
     * @param  array|Collection  $ids
     * @param  string            $folder
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function moveFiles(array|Collection $ids, string $folder): bool
    {
        $this->hasPermissions();
        $oids = $this->ensureObjectIds($ids);
        $this->updateMany(['_id' => ['$in' => $oids->unwrap()]], ['$set' => ['folder' => $folder]]);
        return true;
    }

    /**
     *
     * Move all files from one folder to another
     *
     * @param  string  $folder
     * @param  string  $moveTo
     * @param  string  $siteId
     * @return void
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function moveAllFiles(string $folder, string $moveTo, string $siteId = 'default'): void
    {
        $this->hasPermissions();
        $this->updateMany(['folder' => $folder, 'site_id' => $siteId], ['$set' => ['folder' => $moveTo]]);
    }
}