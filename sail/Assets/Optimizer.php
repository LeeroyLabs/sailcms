<?php

namespace SailCMS\Assets;

use Imagick;
use ImagickException;

class Optimizer
{
    /**
     *
     * Optimize an image to webp
     *
     * @param  string  $img
     * @param  float   $quality
     * @param  bool    $keepAlpha
     * @return string
     * @throws ImagickException
     *
     */
    public static function process(string $img, float $quality = 92, bool $keepAlpha = true): string
    {
        $image = new Imagick();
        $image->readImageBlob($img);

        $image->setImageCompressionQuality($quality);
        $image->setImageFormat('webp');
        $image->setOption('webp:lossless', true);
        return $image->getImageBlob();
    }
}