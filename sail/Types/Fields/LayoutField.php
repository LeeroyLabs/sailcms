<?php

namespace SailCMS\Types\Fields;

use SailCMS\Collection;
use SailCMS\Contracts\DatabaseType;
use SailCMS\Types\LocaleField;
use stdClass;

class LayoutField implements DatabaseType
{
    public function __construct(
        public readonly LocaleField $labels,
        public readonly string $handle,
        public readonly Collection $configs
    ) {
    }

    public function toDBObject(): stdClass
    {
        $configs = new Collection();
        $this->configs->each(function ($key, $value) use (&$configs)
        {
            $configs->push($value->toDBObject());
        });

        return (object)[
            'labels' => $this->labels->toDBObject(),
            'handle' => $this->handle,
            'configs' => $configs->unwrap()
        ];
    }
}