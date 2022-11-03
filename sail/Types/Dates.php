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
        return $dates->toDBObject();
    }

    // TODO: use toDBObject instead for object simplification

    /**
     *
     *
     *
     * @param  Dates  $dates
     * @return array
     *
     */
    static public function deleted(Dates $dates): array
    {
        $now = time();

        $newDates = new Dates($dates->created, $dates->updated, $dates->published, $now);
        return $newDates->toDBObject();
    }

    /**
     *
     * Transform class to an array
     *
     * @return array
     *
     */
    public function toDBObject(): array
    {
        return [
            'created' => $this->created,
            'updated' => $this->updated,
            'published' => $this->published,
            'deleted' => $this->deleted,
        ];
    }
}