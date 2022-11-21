<?php

namespace SailCMS\Types\Fields;

use SailCMS\Collection;
use SailCMS\Contracts\DatabaseType;

class LayoutField implements DatabaseType
{
    public function __construct(
        public readonly string $handle,
        public readonly Collection $configs
    ) {
    }

    public function toDBObject(): array
    {
        $configs = new Collection();
        $this->configs->each(function ($key, $value) use (&$configs)
        {
            $configs->push($value->toDBObject());
        });
        return [
            'handle' => $this->handle,
            'configs' => $configs
        ];
    }
}