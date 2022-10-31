<?php

namespace SailCMS;

use League\Flysystem\FilesystemException;
use SailCMS\Assets\Optimizer;
use SailCMS\Errors\AssetException;

class Assets
{
    /**
     * features
     *
     * add
     * update
     * delete
     * list
     *
     * transform
     *
     *
     *
     */


    private string $path;

    public function __construct()
    {
        $this->path = $_ENV['SETTINGS']->get('assets.path');
    }

    /**
     *
     * Write file to set asset protocol
     *
     * @param  string  $file
     * @param  string  $filename
     * @return void
     * @throws AssetException
     * @throws FilesystemException
     *
     */
    public function add(string $file, string $filename)
    {
        $fs = Filesystem::manager();
        $data = base64_decode($file);
        $size = strlen($file);
        $maxSize = $_ENV['SETTINGS']->get('assets.maxUploadSize');
        $date = date('Y/m/');
        $sizeMB = (strlen($file) / 1024) / 1024;

        // Prepend with a / if the path does not end with /
        if (!str_ends_with($this->path, '/')) {
            $date = '/' . $date;
        }

        if ($sizeMB > $_ENV['SETTINGS']->get('assets.maxUploadSize')) {
            throw new AssetException("Asset is too big, maximum upload size is {$maxSize}mb", 403);
        }

        $optimize = $_ENV['SETTINGS']->get('assets.optimizeOnUpload');
        $transforms = $_ENV['SETTINGS']->get('assets.onUploadTransforms');
        $quality = $_ENV['SETTINGS']->get('assets.transformQuality');

        $info = explode('.', $filename);
        $ext = end($info);

        $validImage = match ($ext) {
            'jpeg', 'jpg', 'png' => true,
            default => false,
        };

        // Optimize to webp
        if ($validImage && $optimize) {
            $data = Optimizer::process($data, $quality);
            $filename = str_replace($ext, 'webp', $filename);
        }

        // Transform on upload
        if ($transforms->length > 0) {
            $format = $_ENV['SETTINGS']->get('assets.transformOutputFormat');
            $transforms = $_ENV['SETTINGS']->get('assets.onUploadTransforms');

            if ($transforms->length > 0) {
                $transforms->each(function ($key, $transform) use ($format, $quality)
                {
                });
            }

            print_r($transforms);
        }

        $fs->write($this->path . $date . $filename, $data);

        // Write to database (info + meta)
        // TODO: SAVE TO DB
        // TODO: TRANSFORM ON STORE

        // Store file

    }

}