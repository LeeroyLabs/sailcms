<?php

use SailCMS\Models\Category;
use SailCMS\Sail;
use SailCMS\Locale;
use SailCMS\Types\LocaleField;

include_once __DIR__ . '/mock/db.php';

beforeAll(function ()
{
    Sail::setupForTests(__DIR__);
});

test('Get category tree', function ()
{
    $category = new Category();
    $tree = $category->getList('');
    expect($tree->length)->toBeGreaterThanOrEqual(2);
})->group('categories');

test('Create a category', function ()
{
    $category = new Category();
    $category->create(new LocaleField(['en' => 'Unit Test', 'fr' => 'Test Unitaire']), '');
    $cat = Category::getBySlug('unit-test', 'main');

    expect($cat)->not->toBeNull();
})->group('categories');

test('Update a category', function ()
{
    $category = new Category();
    $cat = Category::getBySlug('unit-test', 'main');

    expect($cat)->not->toBeNull();

    if ($cat) {
        $ret = $category->update($cat->_id, new LocaleField(['en' => 'Unit Test 2', 'fr' => 'Test Unitaire 2']), '');
        expect($ret)->toBeTrue();
    }
})->group('categories');

test('Delete a category', function ()
{
    $ret = Category::deleteBySlug('unit-test', 'main');
    expect($ret)->toBeTrue();
})->group('categories');