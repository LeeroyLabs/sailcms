<?php

namespace SailCMS\Assets;

class Optimizer
{
    /**
     *
     * Optimize an image to webp
     *
     * @param  string  $img
     * @param  float   $quality
     * @param  bool    $keepAlpha
     * @return mixed
     *
     */
    public static function process(string $img, float $quality = 0.92, bool $keepAlpha = true): mixed
    {
        $img = imagecreatefromstring($img);

        $w = imagesx($img);
        $h = imagesy($img);
        $webp = imagecreatetruecolor($w, $h);

        if ($keepAlpha) {
            imageAlphaBlending($webp, false);
            imagesavealpha($webp, true);

            $trans = imagecolorallocatealpha($webp, 0, 0, 0, 127);
            imagefilledrectangle($webp, 0, 0, $w - 1, $h - 1, $trans);
        }

        $q = $quality * 100;

        if ($q > 100) {
            $q = 100;
        }

        imagecopy($webp, $img, 0, 0, 0, 0, $w, $h);

        ob_start();
        imagewebp($webp, null, $q);
        return ob_get_clean();
    }
}