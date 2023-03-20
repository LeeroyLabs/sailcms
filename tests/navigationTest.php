<?php

use SailCMS\Models\Navigation;
use SailCMS\Sail;
use SailCMS\Types\NavigationElement;

include_once __DIR__ . '/mock/db.php';

beforeAll(function ()
{
    Sail::setupForTests(__DIR__);
});

test('Fetch navigation called "sidebar"', function ()
{
    $nav = Navigation::getByName('sidebar');
    \SailCMS\Debug::ray($nav);
})->group('navigation');

//test('Create a navigation called "sidebar" with 100% array', function ()
//{
//    $navigation = new Navigation();
//    $structure = [
//        [
//            'label' => 'Home',
//            'url' => '/',
//            'external' => false,
//            'children' => []
//        ],
//        [
//            'label' => 'Products',
//            'url' => '/products',
//            'external' => false,
//            'children' => [
//                [
//                    'label' => 'Super Product',
//                    'url' => '/products/super-product',
//                    'external' => false,
//                    'children' => []
//                ]
//            ]
//        ],
//        [
//            'label' => 'About',
//            'url' => '/about',
//            'external' => false,
//            'children' => []
//        ]
//    ];
//
//    $navigation->create('sidebar', $structure, 'en');
//    $nav = Navigation::getByName('sidebar');
//
//    expect($nav)->not->toBe(null)->and(count($nav->structure->get()))->toBe(3);
//});
//
//test('Create a navigation called "sidebar2" with some elements already NavigationElement', function ()
//{
//    $navigation = new Navigation();
//    $structure = [
//        new NavigationElement('Home', '/', false, []),
//        [
//            'label' => 'Products',
//            'url' => '/products',
//            'external' => false,
//            'children' => [
//                [
//                    'label' => 'Super Product',
//                    'url' => '/products/super-product',
//                    'external' => false,
//                    'children' => []
//                ]
//            ]
//        ],
//        [
//            'label' => 'About',
//            'url' => '/about',
//            'external' => false,
//            'children' => []
//        ]
//    ];
//
//    $navigation->create('sidebar2', $structure, 'en');
//});