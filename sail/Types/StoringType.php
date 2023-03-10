<?php

namespace SailCMS\Types;

enum StoringType: string
{
    case INTEGER = 'integer';
    case FLOAT = 'float';
    case STRING = 'string';
    case BOOLEAN = 'boolean';
    case ARRAY = 'array';
    case DATE = 'date';
}