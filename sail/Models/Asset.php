<?php

namespace SailCMS\Models;

use ImagickException;
use League\Flysystem\FilesystemException;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use SailCMS\ACL;
use SailCMS\Assets\Optimizer;
use SailCMS\Assets\Size;
use SailCMS\Assets\Transformer;
use SailCMS\Collection;
use SailCMS\Database\BaseModel;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\FileException;
use SailCMS\Filesystem;
use SailCMS\Types\Listing;
use SailCMS\Types\Pagination;
use SailCMS\Types\QueryOptions;

// TODO: Update (set title)
// TODO: Delete

class Asset extends BaseModel
{
    public string $filename;
    public string $name;
    public string $title;
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
            'transforms'
        ];
    }

    protected function processOnFetch(string $field, mixed $value): mixed
    {
        if ($field === 'size') {
            return new Size($value->width, $value->height);
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
    public function getById(string|ObjectId $id): ?Asset
    {
        return $this->findById($id)->exec();
    }

    /**
     *
     * Get list of assets
     *
     * @param  int     $page
     * @param  int     $limit
     * @param  string  $search
     * @param  string  $sort
     * @param  int     $direction
     * @return Listing
     * @throws ACLException
     * @throws DatabaseException
     *
     */
    public function getList(int $page = 1, int $limit = 50, string $search = '', string $sort = 'name', int $direction = BaseModel::SORT_ASC): Listing
    {
        if (ACL::hasPermission(User::$currentUser, ACL::write('asset'))) {
            $offset = $page * $limit - $limit;
            $query = [];

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

        return Listing::empty();
    }

    /**
     *
     * Upload an asset, process it if it's an image
     *
     * @param  string                $data
     * @param  string                $filename
     * @param  ObjectId|User|string  $uploader
     * @return string
     * @throws DatabaseException
     * @throws FileException
     * @throws ImagickException
     * @throws FilesystemException
     *
     */
    public function upload(string $data, string $filename, ObjectId|User|string $uploader = ''): string
    {
        $fs = Filesystem::manager();

        // Options
        $adapter = $_ENV['SETTINGS']->get('assets.adapter');
        $maxSize = $_ENV['SETTINGS']->get('assets.maxUploadSize');
        $optimize = $_ENV['SETTINGS']->get('assets.optimizeOnUpload');
        $transforms = $_ENV['SETTINGS']->get('assets.onUploadTransforms');
        $quality = $_ENV['SETTINGS']->get('assets.transformQuality');

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
            }

            // Size of the file (width/height)
            $size = Transformer::getImageSizeFromSource($data);
        } else {
            // Not applicable
            $size = new Size(0, 0);
        }

        // Store asset
        $fs->write($path . $timePath . $filename, $data);

        // Determine user that uploaded it, if possible
        $uploader_id = '';

        if (is_string($uploader)) {
            $uploader_id = $uploader;
        } elseif (is_object($uploader) && get_class($uploader) === ObjectId::class) {
            $uploader_id = (string)$uploader;
        } elseif (is_object($uploader) && get_class($uploader) === User::class) {
            $uploader_id = (string)$uploader->_id;
        }

        // Create entry
        $id = $this->insert([
            'filename' => $path . $timePath . $filename,
            'name' => basename($filename),
            'title' => basename($filename),
            'url' => $fs->publicUrl($basePath . $timePath . $filename),
            'is_image' => $isImage,
            'filesize' => $sizeBytes,
            'size' => $size,
            'uploader_id' => $uploader_id,
            'public' => ($uploader_id === ''),
            'transforms' => [],
            'created_at' => time()
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
            return $_ENV['SITE_URL'] . $fs->publicUrl($basePath . $timePath . $filename);
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
            $format = $_ENV['SETTINGS']->get('assets.transformOutputFormat');
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
                        return $_ENV['SITE_URL'] . $cache->url;
                    }

                    return $cache->url;
                }

                // Miss, generate it
                $transform = new Transformer($this->filename);

                if ($crop !== '') {
                    $transform->resizeAndCrop($width, $height, $crop)->save($transformFilename, $format);
                } else {
                    $transform->resize($width, $height);
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
                    return $_ENV['SITE_URL'] . $fs->publicUrl($fileURL);
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
        $model = new Asset();
        $asset = $model->getById($id);

        if ($asset && $asset->is_image) {
            return $asset->transform($name, $width, $height, $crop);
        }

        return $asset->url ?? '';
    }
}