<?php

namespace SailCMS\Types;

use SailCMS\Collection;
use SailCMS\Contracts\Castable;

class SeoSettings implements Castable
{
    public string $separator_character;
    public string $sitename;
    public string $sitename_position;
    public string $gtag;

    public function __construct(array $values)
    {
        $this->sitename_position = TitlePosition::fromName($values['sitename_position'])->value;
        $this->separator_character = $values['separator_character'];
        $this->sitename = $values['sitename'];
        $this->gtag = $values['gtag'];
    }

    public function castFrom(): array
    {
        return [
            'separator_character' => $this->separator_character,
            'sitename' => $this->sitename,
            'sitename_position' => $this->sitename_position,
            'gtag' => $this->gtag
        ];
    }

    public function castTo(mixed $value): SeoSettings
    {
        return new self($value);
    }
}