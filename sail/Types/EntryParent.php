<?php

namespace SailCMS\Types;

use SailCMS\Contracts\DatabaseType;

class EntryParent implements DatabaseType
{
    public function __construct(
        public readonly string $handle,
        public readonly string $parent_id
    )
    {
    }

    /**
     *
     * Return an empty entry parent
     *
     * @return array
     *
     */
    public static function init(): array
    {
        return [
            'handle' => '',
            'parent_id' => ''
        ];
    }

    /**
     * @return array
     */
    public function toDBObject(): array
    {
        return [
            'handle' => $this->handle ?? '',
            'parent_id' => $this->parent_id ?? ''
        ];
    }
}