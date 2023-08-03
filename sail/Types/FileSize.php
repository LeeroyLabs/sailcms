<?php

namespace SailCMS\Types;

use SailCMS\Locale;
use Stringable;

class FileSize implements Stringable
{
    public readonly int $bytes;
    public readonly string $size;
    public readonly string $unit;
    public readonly string $formatted;

    public function __construct(int $bytes)
    {
        if (Locale::current() === 'fr') {
            $units = ['o', 'Ko', 'Mo', 'Go', 'To', 'Po', 'Eo', 'Zo', 'Yo'];
        } else {
            $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        }

        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        $nsep = (Locale::current() === 'fr') ? ' ' : ',';
        $dsep = (Locale::current() === 'fr') ? ',' : '.';
        $size = number_format($bytes / (1024 ** $power), 2, $dsep, $nsep);
        $unit = $units[$power];

        $this->bytes = $bytes;
        $this->size = $size;
        $this->unit = $unit;
        $this->formatted = $size . $unit;
    }

    public function __toString(): string
    {
        return $this->formatted;
    }
}