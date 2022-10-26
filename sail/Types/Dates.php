<?php

namespace SailCMS\Types;

class Dates
{
    public function __construct(public readonly ?int $created, public readonly ?int $updated, public readonly ?int $published, public readonly ?int $deleted)
    {
    }

    /**
     * Get an array of dates when we create a element
     *
     * @param  bool  $publish
     * @return array
     */
    static public function atCreation(bool $publish=false):array {
        $now = time();
        $publishDate = null;
        if ($publish) {
            $publishDate = $now;
        }
        $dates = new Dates($now, $now, $publishDate, null);
        return $dates->toArray();
    }

    /**
     * Transform class to an array
     *  TODO maybe extend a type that does that dynamically
     *
     * @return array
     */
    public function toArray():array {
        return [
            'created' => $this->created,
            'updated' => $this->updated,
            'published' => $this->published,
            'deleted' => $this->deleted,
        ];
    }
}