<?php

use SailCMS\Cache;
use SailCMS\Models\Category;

include_once __DIR__ . '/mock/db.php';

beforeAll(function ()
{
    \SailCMS\Locale::setAvailableLocales(['fr', 'en']);
});

test('Get category tree', function ()
{
    $category = new Category();
    $tree = $category->getList('');
    expect($tree->length)->toBe(3);
});