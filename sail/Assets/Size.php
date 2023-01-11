<?php

namespace SailCMS\Assets;

use SailCMS\Contracts\Castable;

readonly class Size implements Castable
{
    public function __construct(public int $width = 0, public int $height = 0) { }

    /**
     *
     * Cast to simpler format from Size
     *
     * @return array
     *
     */
    public function castFrom(): array
    {
        return [
            'width' => $this->width,
            'height' => $this->height
        ];
    }

    /**
     *
     * Cast to Size
     *
     * @param  mixed  $value
     * @return Size
     *
     */
    public function castTo(mixed $value): Size
    {
        return new Size($value->width, $value->height);
    }
}