<?php

namespace SailCMS\Types;

use SailCMS\Collection;
use SailCMS\Contracts\Castable;
use stdClass;

class LayoutField implements Castable
{
    /**
     *
     * Structure to save a field in an entry layout schema
     *
     */
    public function __construct(
        public readonly ?LocaleField $labels = null,
        public readonly string       $handle = '',
        public readonly Collection   $configs = new Collection([])
    )
    {
    }

    public function castFrom(): stdClass
    {
        $configs = new Collection();

        $this->configs->each(function ($key, $value) use (&$configs) {
            if ($value->get('options') instanceof Collection) {
                $value->pushKeyValue('options', $value->get('options')->castFrom());
            }

            $configs->pushKeyValue($key, $value->castFrom());
        });

        return (object)[
            'labels' => $this->labels->castFrom(),
            'handle' => $this->handle,
            'configs' => $configs->unwrap()
        ];
    }

    public function castTo(mixed $value): self
    {
        if (is_array($value->configs->get('options'))) {
            $options = new Collection($value->configs->get('options'));
            $value->configs->pushKeyValue('options', $options);
        }
        return new self($value->labels, $value->handle, $value->configs);
    }
}