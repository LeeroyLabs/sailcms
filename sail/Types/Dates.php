<?php

namespace SailCMS\Types;

use SailCMS\Contracts\Castable;

readonly class Dates implements Castable
{
    public function __construct(
        public ?int $created = 0,
        public ?int $updated = 0,
        public ?int $published = 0,
        public ?int $deleted = 0
    ) {
    }

    /**
     *
     * Get an array of dates when we create an element
     *
     * @param  bool  $published
     * @return array
     *
     */
    public static function init(bool $published = false): array
    {
        $now = time();
        $publishDate = null;

        if ($published) {
            $publishDate = $now;
        }

        $dates = new Dates($now, $now, $publishDate, null);
        return $dates->castFrom();
    }

    /**
     *
     * Update the deleted attribute for a given Dates object
     *
     * @param  Dates  $dates
     * @return array
     *
     */
    public static function updated(Dates $dates): array
    {
        $now = time();
        $newDates = new Dates($dates->created, $now, $dates->published, $dates->deleted);
        return $newDates->castFrom();
    }


    /**
     *
     * Update the deleted attribute for a given Dates object
     *
     * @param  Dates  $dates
     * @return array
     *
     */
    public static function deleted(Dates $dates): array
    {
        $now = time();
        $newDates = new Dates($dates->created, $dates->updated, $dates->published, $now);
        return $newDates->castFrom();
    }

    /**
     *
     * Cast to simpler format from Username
     *
     * @return array
     *
     */
    public function castFrom(): array
    {
        return [
            'created' => $this->created,
            'updated' => $this->updated,
            'published' => $this->published ?? 0,
            'deleted' => $this->deleted ?? 0,
        ];
    }

    /**
     *
     * Cast to Dates
     *
     * @param  mixed  $value
     * @return Dates
     *
     */
    public function castTo(mixed $value): Dates
    {
        return new self($value->created, $value->updated, $value->published ?? 0, $value->deleted ?? 0);
    }
}