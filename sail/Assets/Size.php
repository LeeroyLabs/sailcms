<?php

namespace SailCMS\Assets;

use SailCMS\Contracts\DatabaseType;

class Size implements DatabaseType
{
    public function __construct(public readonly int $width, public readonly int $height) { }

    /**
     *
     * @return \stdClass|array<string, int>
     *
     */
    public function toDBObject(): \stdClass|array
    {
        return [
            'width' => $this->width,
            'height' => $this->height
        ];
    }
}