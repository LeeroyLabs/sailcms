<?php

namespace SailCMS\Assets;

use PHPImageWorkshop\Core\ImageWorkshopLayer;
use PHPImageWorkshop\ImageWorkshop;
use SailCMS\Filesystem;

class Transformer
{
    public const CROP_TL = 'LT';
    public const CROP_TM = 'MT';
    public const CROP_TR = 'RT';
    public const CROP_LC = 'LM';
    public const CROP_CC = 'MM';
    public const CROP_CR = 'RM';
    public const CROP_BL = 'LB';
    public const CROP_BM = 'MB';
    public const CROP_BR = 'RB';

    public const ORIENTATION_HORIZONTAL = 'horizontal';
    public const ORIENTATION_VERTICAL = 'vertical';

    public const OUTPUT_JPEG = 'jpg';
    public const OUTPUT_PNG = 'png';
    public const OUTPUT_WEBP = 'webp';

    private ImageWorkshopLayer $image;

    public function __construct(string $path)
    {
        $fs = Filesystem::manager();
        $ctx = $fs->read($path);
        $resource = imagecreatefromstring($ctx);
        $this->image = ImageWorkshop::initFromResourceVar($resource);
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
        $this->image->resizeInPixel($width, $height, $keepProportions);
        return $this;
    }

    /**
     *
     * Resize by the smallest of the 2 measurements and optionally keep proportions
     *
     * @param  int   $size
     * @param  bool  $keepProportions
     * @return $this
     *
     */
    public function resizeByNarrower(int $size, bool $keepProportions = true): static
    {
        $this->image->resizeByNarrowSideInPixel($size, $keepProportions);
        return $this;
    }

    /**
     *
     * Resize by the largest of the 2 measurements and optionally keep proportions
     *
     * @param  int   $size
     * @param  bool  $keepProportions
     * @return $this
     *
     */
    public function resizeByLargest(int $size, bool $keepProportions = true): static
    {
        $this->image->resizeByLargestSideInPixel($size, $keepProportions);
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
        $this->image->resize($width, $height);
        $this->image->cropInPixel($width, $height, 0, 0, $crop);
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
    public function resizeAndCropCustom(int $width, int $height, int $x, int $y, string $crop = Transformer::CROP_TL): static
    {
        $this->image->resize($width, $height);
        $this->image->cropInPixel($width, $height, $x, $y, $crop);
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
        $this->image->applyFilter(IMG_FILTER_NEGATE);
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
        $this->image->applyFilter(IMG_FILTER_GRAYSCALE);
        return $this;
    }

    /**
     *
     * Get the ImageWorkshopLayer from the instance
     *
     * @return ImageWorkshopLayer
     *
     */
    public function getUnderlyingLayer(): ImageWorkshopLayer
    {
        return $this->image;
    }

    /**
     *
     * Add a layer on top of the image (for watermarks, or effects)
     *
     * @param  Transformer  $transform
     * @return $this
     *
     */
    public function addLayerOnTop(Transformer $transform): static
    {
        $this->image->addLayerOnTop($transform->getUnderlyingLayer());
        return $this;
    }

    public function save(string $name, string $type = Transformer::OUTPUT_WEBP): string
    {
        $image = $this->image->getResult();
        $fs = Filesystem::manager();

        switch ($type)
        {
            case static::OUTPUT_JPEG:
                $fs->write($name);
                break;

            case static::OUTPUT_PNG:
                break;

            default:
            case static::OUTPUT_WEBP:
                break
        }
    }
}