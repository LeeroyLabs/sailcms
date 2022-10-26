<?php

namespace SailCMS\Types;

enum EntryStatus: string
{
    case LIVE = 'live';
    case INACTIVE = 'inactive';
    case DELETED = 'deleted';

    /**
     * @param  string  $status
     * @return EntryStatus
     */
    static public function getStatusEnum(string $status): EntryStatus
    {
        match ($status) {
            self::LIVE->value => $result = self::LIVE,
            self::INACTIVE->value => $result = self::INACTIVE,
            self::DELETED->value => $result = self::DELETED,
        };

        return $result;
    }
}