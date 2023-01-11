<?php

namespace SailCMS\Models;

use SailCMS\Database\Model;
use SailCMS\Types\NavigationStructure;

class Navigation extends Model
{
    protected string $collection = 'navigations';
    protected array $fields = ['name', 'structure'];
    protected array $casting = [
        'structure' => NavigationStructure::class
    ];

}