<?php

namespace SailCMS\Types;

use SailCMS\Collection;
use SailCMS\Contracts\Castable;

class SeoSettings implements Castable
{
    public string $separator_character;
    public string $sitename;
    public TitlePosition $sitename_position;
    public bool $gtag;

    public function __construct(array $values)
    {
        foreach ($values as $key => $value) {
            if ($key === 'sitename_position') {
                $this->sitename_position = $value->value;
            }
        }

        $this->separator_character = $values['separator_character'];
        $this->gtag = $values['gtag'];
        $this->sitename = $values['sitename'];
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