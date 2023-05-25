<?php

namespace SailCMS\Assets;

use ImagickException;
use Intervention\Image\Image;
use Intervention\Image\ImageManager;
use League\Flysystem\FilesystemException;
use SailCMS\Filesystem;

class Transformer
{
    public const CROP_TL = 'top-left';
    public const CROP_TM = 'top';
    public const CROP_TR = 'to-right';
    public const CROP_LC = 'left';
    public const CROP_CC = 'center';
    public const CROP_CR = 'right';
    public const CROP_BL = 'bottom-left';
    public const CROP_BM = 'bottom';
    public const CROP_BR = 'bottom-right';

    public const ORIENTATION_HORIZONTAL = 'h';
    public const ORIENTATION_VERTICAL = 'v';

    public const OUTPUT_JPEG = 'jpg';
    public const OUTPUT_PNG = 'png';
    public const OUTPUT_WEBP = 'webp';

    private Image $image;

    /**
     *
     * Setup transformer with a file
     *
     * @param  string  $path
     * @param  bool    $isBlob
     * @throws FilesystemException
     *
     */
    public function __construct(string $path, bool $isBlob = false)
    {
        $manager = new ImageManager(['driver' => 'imagick']);

        if (!$isBlob) {
            $fs = Filesystem::manager();
            $ctx = $fs->readStream($path);
            $this->image = $manager->make($ctx)->orientate();
        } else {
            $manager->make($path)->orientate();
        }
    }

    /**
     *
     * Resize with or without both measurements and optionally keep proportions
     *
     * @param  int       $width
     * @param  int|null  $height
     * @param  bool      $keepProportions
     * @return $this
     *
     */
    public function resize(int $width, ?int $height = null, bool $keepProportions = true): static
    {
        $this->image->resize($width, $height, function ($constraint) use ($keepProportions)
        {
            if ($keepProportions) {
                $constraint->aspectRatio();
            }

            $constraint->upsize();
        });

        return $this;
    }

    /**
     *
     * Resize and crop center
     *
     * @param  int     $width
     * @param  int     $height
     * @param  string  $crop
     * @return $this
     *
     */
    public function resizeAndCrop(int $width, int $height, string $crop = Transformer::CROP_CC): static
    {
        $this->image->fit($width, $height, function ($constraint)
        {
            $constraint->upsize();
        }, $crop);

        return $this;
    }

    /**
     *
     * Resize and crop with custom crop configuration
     *
     * @param  int     $width
     * @param  int     $height
     * @param  int     $x
     * @param  int     $y
     * @param  string  $crop
     * @return $this
     *
     */
    public function crop(int $width, int $height, int $x, int $y, string $crop = Transformer::CROP_TL): static
    {
        $this->image->crop($width, $height, $x, $y);
        return $this;
    }

    /**
     *
     * Flip the image in the given orientation
     *
     * @param  string  $orientation
     * @return $this
     *
     */
    public function flip(string $orientation = Transformer::ORIENTATION_HORIZONTAL): static
    {
        $this->image->flip($orientation);
        return $this;
    }

    /**
     *
     * Rotate the image
     *
     * @param  int  $degrees
     * @return $this
     *
     */
    public function rotate(int $degrees): static
    {
        $this->image->rotate($degrees);
        return $this;
    }

    /**
     *
     * Invert all colors to look like a film negative
     *
     * @return $this
     *
     */
    public function negative(): static
    {
        $this->image->invert();
        return $this;
    }

    /**
     *
     * Apply a grayscale filter on the image
     *
     * @return $this
     *
     */
    public function grayscale(): static
    {
        $this->image->greyscale();
        return $this;
    }

    /**
     *
     * Get the Image from the instance
     *
     * @return Image
     *
     */
    public function getUnderlyingLayer(): Image
    {
        return $this->image;
    }

    /**
     *
     * Get the image size in bytes
     *
     * @return mixed
     *
     */
    public function filesize(): mixed
    {
        return $this->image->filesize();
    }

    /**
     *
     * Save image
     *
     * @param  string  $name
     * @param  string  $type
     * @param  bool    $blob
     * @return string
     * @throws FilesystemException
     * @throws ImagickException
     */
    public function save(string $name, string $type = Transformer::OUTPUT_WEBP, bool $blob = false): string
    {
        $image = $this->image->getCore();
        $fs = Filesystem::manager();

        $quality = setting('assets.transformQuality', 92);

        $imagick = new \Imagick();
        $imagick->readImageBlob($image);
        $imagick->setImageCompressionQuality($quality);

        switch ($type) {
            case static::OUTPUT_JPEG:
                $imagick->setImageFormat('jpg');
                break;

            case static::OUTPUT_PNG:
                $imagick->setImageFormat('png');
                break;

            default:
            case static::OUTPUT_WEBP:
                $imagick->setImageFormat('webp');
                $imagick->setOption('webp:lossless', 'true');
                break;
        }

        if ($blob) {
            return $imagick->getImageBlob();
        }

        $fs->write($name, $imagick->getImageBlob(), ['visibility' => 'public']);
        return '';
    }

    /**
     *
     * Get image size given the image source
     *
     * @param  mixed  $source
     * @return Size
     * @throws ImagickException
     *
     */
    public static function getImageSizeFromSource(mixed $source): Size
    {
        $img = new \Imagick();
        $img->readImageBlob($source);

        return new Size($img->getImageWidth(), $img->getImageHeight());
    }
}