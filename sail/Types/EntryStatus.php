<?php

namespace SailCMS\Types;

enum EntryStatus: string
{
    case LIVE = 'live';
    case INACTIVE = 'inactive';
    case TRASH = 'trash';
}