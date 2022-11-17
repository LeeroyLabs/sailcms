<?php

use SailCMS\Models\Category;
use SailCMS\Sail;
use SailCMS\Locale;
use SailCMS\Types\LocaleField;

include_once __DIR__ . '/mock/db.php';

beforeAll(function ()
{
    Locale::setAvailableLocales(['fr', 'en']);
    $_ENV['SITE_URL'] = 'http://localhost:8888';
    Sail::setAppState(Sail::STATE_CLI);
});

test('Get category tree', function ()
{
    $category = new Category();
    $tree = $category->getList('');
    expect($tree->length)->toBe(3);
});

test('Create a category', function ()
{
    $category = new Category();
    $category->create(new LocaleField(['en' => 'Unit Test', 'fr' => 'Test Unitaire']), '');
    $cat = Category::getBySlug('unit-test');

    expect($cat)->not->toBeNull();
});

test('Update a category', function ()
{
    $category = new Category();
    $cat = Category::getBySlug('unit-test');

    expect($cat)->not->toBeNull();

    if ($cat) {
        $ret = $category->update($cat->_id, new LocaleField(['en' => 'Unit Test 2', 'fr' => 'Test Unitaire 2']), '');
        expect($ret)->toBeTrue();
    }
});

test('Delete a category', function ()
{
    $ret = Category::deleteBySlug('unit-test');
    expect($ret)->toBeTrue();
});