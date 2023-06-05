<?php

namespace SailCMS\Types;

enum FieldCategory: string
{
    case TEXT = 'text';
    case SELECT = 'select';
    case DATETIME = 'datetime';
    case SPECIAL = 'special';
}