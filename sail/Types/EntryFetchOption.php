<?php

namespace SailCMS\Types;

enum EntryFetchOption: string {
    case ALL = 'all';
    case ASSET = 'assets';
    case ENTRY = 'entries';
    case CATEGORY = 'categories';

    public static function fromName(string $name): EntryFetchOption
    {
        return constant("self::" . $name);
    }

    public static function getAll(): array
    {
        return ['ASSET', 'ENTRY', 'CATEGORY'];
    }
}