<?php

use SailCMS\Models\Navigation;
use SailCMS\Sail;
use SailCMS\Types\NavigationElement;

include_once __DIR__ . '/mock/db.php';

beforeAll(function () {
    Sail::setupForTests(__DIR__);
});

test('Create a navigation called "sidebar" with 100% array', function () {
    $navigation = new Navigation();
    $structure = [
        [
            'label' => 'Home',
            'url' => '/',
            'is_entry' => false,
            'is_category' => false,
            'entry_id' => '',
            'external' => false,
            'children' => []
        ],
        [
            'label' => 'Products',
            'url' => '/products',
            'is_entry' => false,
            'is_category' => false,
            'entry_id' => '',
            'external' => false,
            'children' => [
                [
                    'label' => 'Super Product',
                    'url' => '/products/super-product',
                    'is_entry' => false,
                    'is_category' => false,
                    'entry_id' => '',
                    'external' => false,
                    'children' => []
                ]
            ]
        ],
        [
            'label' => 'About',
            'url' => '/about',
            'is_entry' => false,
            'is_category' => false,
            'entry_id' => '',
            'external' => false,
            'children' => []
        ]
    ];

    $navigation->create('sidebar', 'sidebar', $structure, 'en');
    $nav = Navigation::getBySlug('sidebar');

    expect($nav)->not->toBe(null)->and(count($nav->structure->get()))->toBe(3);
})->group('navigation');

test('Create a navigation called "sidebar2" with some elements already NavigationElement', function () {
    $navigation = new Navigation();
    $structure = [
        new NavigationElement('Home', '/', false, false, '', false, []),
        [
            'label' => 'Products',
            'url' => '/products',
            'is_entry' => false,
            'entry_id' => '',
            'external' => false,
            'is_category' => false,
            'children' => [
                [
                    'label' => 'Super Product',
                    'url' => '/products/super-product',
                    'is_entry' => false,
                    'is_category' => false,
                    'entry_id' => '',
                    'external' => false,
                    'children' => []
                ]
            ]
        ],
        [
            'label' => 'About',
            'url' => '/about',
            'is_entry' => false,
            'is_category' => false,
            'entry_id' => '',
            'external' => false,
            'children' => []
        ]
    ];

    $navigation->create('sidebar2', 'sidebar2', $structure, 'en');
    $nav = Navigation::getBySlug('sidebar2');
    expect($nav)->not->toBe(null)->and(count($nav->structure->get()))->toBe(3);
})->group('navigation');

test('Fetch navigation called "sidebar"', function () {
    $nav = Navigation::getBySlug('sidebar');
    expect($nav)->not->toBeNull()->and($nav->structure->get())->toBeArray()->and(count($nav->structure->get()))->toBe(3);
})->group('navigation');

test('Update navigation called "sidebar"', function () {
    $nav = Navigation::getBySlug('sidebar');

    $navigation = new Navigation();
    $navigation->update($nav->id, 'sidebar', $nav->slug, $nav->structure->get(), 'fr');
    $nav = Navigation::getBySlug('sidebar');

    expect($nav)->not->toBeNull()->and($nav->locale)->toBe('fr');
})->group('navigation');

test('Get all navigations', function () {
    $navigations = Navigation::getList('title', -1);
    expect(count($navigations))->toBe(2)
        ->and($navigations[0]->slug)->toBe('sidebar2');

    $navigations = Navigation::getList('title', 1, 'fr');
    expect(count($navigations))->toBe(1);
});

test('Delete navigation by name', function () {
    $navigation = new Navigation();
    $navigation->deleteByName('sidebar');
    $navigation->deleteByName('sidebar2');

    $nav = Navigation::getBySlug('sidebar');

    expect($nav)->toBeNull();
})->group('navigation');