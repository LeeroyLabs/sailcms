<?php

namespace SailCMS\Types;

enum EntryFetchOption: string {
    case ALL = 'all';
    case ASSET = 'assets';
    case ENTRY = 'entries';
    case CATEGORY = 'categories';
}