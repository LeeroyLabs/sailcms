<?php

namespace SailCMS\Types;

use SailCMS\Collection;
use SailCMS\Contracts\DatabaseType;

class LocaleField implements DatabaseType
{
    private Collection $combinations;

    public function __construct(array|\stdClass $combinations)
    {
        $this->combinations = Collection::init();

        if (!empty($combinations)) {
            foreach ($combinations as $key => $value) {
                $this->combinations->push(['locale' => $key, 'value' => $value]);
            }
        }
    }

    public function __get(string $locale)
    {
        $out = '';

        $this->combinations->find(function ($key, $value) use (&$out, $locale)
        {
            if ($value['locale'] === $locale) {
                $out = $value['value'];
            }
        });

        return $out;
    }

    public function toDBObject(): \stdClass|array
    {
        $out = [];

        foreach ($this->combinations->unwrap() as $data) {
            $out[$data['locale']] = $data['value'];
        }

        return $out;
    }
}