<?php

namespace SailCMS\Types;

enum FieldMode: string
{
    case SEARCHABLE = 'searchable';
    case REPEATABLE = 'repeatable';
    case MULTIPLIABLE = 'multipliable';
}