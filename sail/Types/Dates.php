<?php

namespace SailCMS\Types;

use SailCMS\Contracts\DatabaseType;

class Dates implements DatabaseType
{
    public function __construct(
        public readonly ?int $created,
        public readonly ?int $updated,
        public readonly ?int $published,
        public readonly ?int $deleted
    ) {
    }

    // TODO: Return Collection of dates
    // TODO: rename to init() ?

    /**
     *
     * Get an array of dates when we create an element
     *
     * @param  bool  $published
     * @return array
     *
     */
    static public function init(bool $published = false): array
    {
        $now = time();
        $publishDate = null;
        if ($published) {
            $publishDate = $now;
        }
        $dates = new Dates($now, $now, $publishDate, null);
        return $dates->toArray();
    }

    // TODO: use toDBObject instead for object simplification

    /**
     *
     * Transform class to an array
     *  TODO maybe extend a type that does that dynamically
     *
     * @return array
     *
     */
    public function toArray(): array
    {
        return [
            'created' => $this->created,
            'updated' => $this->updated,
            'published' => $this->published,
            'deleted' => $this->deleted,
        ];
    }

    public function toDBObject(): \stdClass|array
    {
        // TODO: Implement toDBObject() method.
    }
}