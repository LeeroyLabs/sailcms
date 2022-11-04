<?php

namespace SailCMS\Types;

use SailCMS\Contracts\DatabaseType;

class Dates implements DatabaseType
{
    public function __construct(
        public readonly ?float $created,
        public readonly ?float $updated,
        public readonly ?float $published,
        public readonly ?float $deleted
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
        $now = microtime(true);
        $publishDate = null;
        if ($published) {
            $publishDate = $now;
        }
        $dates = new Dates($now, $now, $publishDate, null);
        return $dates->toDBObject();
    }

    /**
     *
     * Update the deleted attribute for a given Dates object
     *
     * @param  Dates  $dates
     * @return array
     *
     */
    static public function updated(Dates $dates): array
    {
        $now = microtime(true);

        $newDates = new Dates($dates->created, $now, $dates->published, $dates->deleted);

        return $newDates->toDBObject();
    }


    /**
     *
     * Update the deleted attribute for a given Dates object
     *
     * @param  Dates  $dates
     * @return array
     *
     */
    static public function deleted(Dates $dates): array
    {
        $now = microtime(true);

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