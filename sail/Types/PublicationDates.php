<?php

namespace SailCMS\Types;

use SailCMS\Contracts\Castable;
use SailCMS\Errors\EntryException;
use SailCMS\Models\EntryPublication;

class PublicationDates implements Castable
{
    /**
     *
     * @param int $published
     * @param int|null $expired
     * @throws EntryException
     *
     */
    public function __construct(
        public int  $published = 0,
        public ?int $expired = 0
    )
    {
        if ($expired !== 0 && $expired <= $published) {
            throw new EntryException(EntryPublication::EXPIRATION_DATE_ERROR[0], EntryPublication::EXPIRATION_DATE_ERROR[1]);
        }
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
            'published' => $this->published,
            'expired' => $this->expired
        ];
    }

    /**
     *
     * Cast to Dates
     *
     * @param mixed $value
     * @return PublicationDates
     * @throws EntryException
     *
     */
    public function castTo(mixed $value): PublicationDates
    {
        return new self($value->published, $value->expired);
    }

    /**
     *
     * Get status
     *
     * @param PublicationDates $dates
     * @return string
     *
     */
    public static function getStatus(PublicationDates $dates): string
    {
        $now = time();

        if ($now < $dates->published) {
            return PublicationStatus::DRAFT->value;
        } else if ($now < $dates->expired) {
            return PublicationStatus::PUBLISHED->value;
        } else {
            return PublicationStatus::EXPIRED->value;
        }
    }
}