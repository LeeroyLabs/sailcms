<?php

namespace SailCMS\Types;

use SailCMS\Contracts\Castable;

class Dates implements Castable
{
    public function __construct(
        public ?int $created = 0,
        public ?int $updated = 0,
        public ?int $deleted = 0
    )
    {
    }

    /**
     *
     * Get an array of dates when we create an element
     *
     * @return array
     *
     */
    public static function init(): array
    {
        $now = time();

        $dates = new Dates($now, $now, null);
        return $dates->castFrom();
    }

    /**
     *
     * Update the deleted attribute for a given Dates object
     *
     * @param Dates $dates
     * @return array
     *
     */
    public static function updated(Dates $dates): array
    {
        $now = time();
        $newDates = new Dates($dates->created, $now, $dates->deleted);
        return $newDates->castFrom();
    }


    /**
     *
     * Update the deleted attribute for a given Dates object
     *
     * @param Dates $dates
     * @return array
     *
     */
    public static function deleted(Dates $dates): array
    {
        $now = time();
        $newDates = new Dates($dates->created, $dates->updated, $now);
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
            'deleted' => $this->deleted ?? 0,
        ];
    }

    /**
     *
     * Cast to Dates
     *
     * @param mixed $value
     * @return Dates
     *
     */
    public function castTo(mixed $value): Dates
    {
        return new self($value->created, $value->updated, $value->deleted ?? 0);
    }
}