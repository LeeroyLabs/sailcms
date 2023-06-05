<?php

namespace SailCMS\Types;

use SailCMS\Contracts\Castable;

class EntryParent implements Castable
{
    public function __construct(
        public string $handle = '',
        public string $parent_id = ''
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
     *
     * Cast to simple version from EntryParent
     *
     * @return array
     *
     */
    public function castFrom(): array
    {
        return [
            'handle' => $this->handle ?? '',
            'parent_id' => $this->parent_id ?? ''
        ];
    }

    /**
     *
     * Cast to EntryParent
     *
     * @param mixed $value
     * @return EntryParent
     *
     */
    public function castTo(mixed $value): self
    {
        return new self($value->handle, $value->parent_id);
    }
}