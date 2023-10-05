<?php

namespace SailCMS\Types;

enum TitlePosition: string
{
    case BEFORE = 'before';
    case AFTER = 'after';

    public static function fromName(string $name): TitlePosition
    {
        return constant("self::" . $name);
    }
}