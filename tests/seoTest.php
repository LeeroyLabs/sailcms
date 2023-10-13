<?php

use SailCMS\Models\Redirection;
use SailCMS\Sail;
use SailCMS\Seo;

include_once __DIR__ . '/mock/db.php';

beforeAll(function ()
{
    Sail::setupForTests(__DIR__);
});

test('Get Global Title', function ()
{
    expect(Seo::global()->title)->toBeString('SailCMS');
})->group('seo');

test('Get Global Description', function ()
{
    expect(Seo::global()->description)->toBeString('This is the description');
})->group('seo');

test('Get Global Separator Character', function ()
{
    expect(Seo::global()->separatorCharacter())->toBeString('-');
})->group('seo');

test('Get Global Site Name Position', function ()
{
    expect(Seo::global()->siteNamePosition())->toBeString('right');
})->group('seo');

test('Get Global Site Name', function ()
{
    expect(Seo::global()->siteName())->toBeString('SailCMS');
})->group('seo');

test('Get Global Full Title Without Page Name', function ()
{
    expect(Seo::global()->fullSiteTitle()->value())->toBeString('SailCMS');
})->group('seo');

test('Get Global Full Title With Page Name', function ()
{
    expect(Seo::global()->fullSiteTitle('My Product')->value())->toBeString('My Product - SailCMS');
})->group('seo');

//test('Create a redirection', function () {
//    $redirection = Redirection::add(
//        '/test',
//        '/',
//        '302'
//    );
//    expect($redirection)->toBeTrue();
//})->group('seo');
//
//test('Update a redirection', function () {
//    $redirectionId = (new Redirection)->getByUrl('/test');
//
//    $redirection = (new Redirection)->update(
//        $redirectionId->id,
//        '/test2',
//        '/',
//        '302'
//    );
//    expect($redirection)->toBeTrue();
//})->group('seo');
//
//test('Get a list of redirections', function () {
//    $redirections = (new Redirection)->getList(
//        1,
//        15
//    );
//    expect($redirections)->not->toBeNull();
//})->group('seo');
//
//
//test('Delete a redirection', function () {
//    $redirectionId = (new Redirection)->getByUrl('/test2');
//
//    $redirection = (new Redirection)->delete($redirectionId->id);
//    expect($redirection)->toBeTrue();
//})->group('seo');