<?php

namespace SailCMS\Types;

use SailCMS\Collection;
use SailCMS\Contracts\Castable;
use stdClass;

readonly class LayoutField implements Castable
{
    /**
     *
     * Structure to save a field in an entry layout schema
     *
     */
    public function __construct(
        public ?LocaleField $labels = null,
        public string       $handle = '',
        public Collection   $configs = new Collection([]),
        public bool         $repeater = false
    )
    {
    }

    /**
     *
     * Cast from the object for the db
     *
     * @return stdClass
     *
     */
    public function castFrom(): stdClass
    {
        $configs = new Collection();

        $this->configs->each(function ($key, $value) use (&$configs) {
            $configs->pushKeyValue($key, $value->castFrom());
        });

        return (object)[
            'labels' => $this->labels->castFrom(),
            'handle' => $this->handle,
            'configs' => $configs->unwrap(),
            'repeater' => $this->repeater
        ];
    }

    /**
     *
     * Cast to a LayoutField from the db
     *
     * @param  mixed  $value
     * @return $this
     *
     */
    public function castTo(mixed $value): self
    {
        return new self($value->labels, $value->handle, $value->configs, $value->repeater);
    }
}