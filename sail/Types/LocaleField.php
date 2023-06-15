<?php

namespace SailCMS\Types;

use MongoDB\Model\BSONDocument;
use SailCMS\Collection;
use SailCMS\Contracts\Castable;

class LocaleField implements Castable
{
    private Collection $combinations;

    public function __construct(array|\stdClass|BSONDocument|LocaleField|null $combinations = null)
    {
        $this->combinations = Collection::init();

        if (is_object($combinations) && get_class($combinations) === BSONDocument::class) {
            $combinations = (array)$combinations;
        }

        if (is_object($combinations) && get_class($combinations) === __CLASS__) {
            $combinations = $combinations->castFrom();
        }

        if (!empty($combinations)) {
            foreach ($combinations as $key => $value) {
                $this->combinations->push(['locale' => $key, 'value' => $value]);
            }
        }
    }

    public function get(string $locale): mixed
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

    public function __get(string $locale)
    {
        return $this->get($locale);
    }

    public function __set(string $locale, string $value): void
    {
        $this->combinations->find(function ($key, $val) use ($locale, $value)
        {
            if ($val['locale'] === $locale) {
                $val['value'] = $value;
            }
        });
    }

    /**
     *
     * Cast to simpler format from LocaleField
     *
     * @return array
     *
     */
    public function castFrom(): array
    {
        $out = [];

        foreach ($this->combinations->unwrap() as $data) {
            $out[$data['locale']] = $data['value'];
        }

        return $out;
    }

    /**
     *
     * Cast to LocaleField
     *
     * @param  mixed  $value
     * @return LocaleField
     *
     */
    public function castTo(mixed $value): LocaleField
    {
        if (is_string($value)) {
            return new self(['fr' => $value, 'en' => $value]);
        }

        return new self($value);
    }
}