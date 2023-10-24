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

test('Get Global Image', function ()
{
    expect(Seo::global()->image)->toContain('https://');
})->group('seo');

test('Get Global Image Tags (FB/X)', function ()
{
    expect(Seo::global()->imageTags)->toContain('<meta property="og:image"');
})->group('seo');

test('Get Global Robots', function ()
{
    expect(Seo::global()->robots)->toContain('all');
})->group('seo');

test('Get Global Robots Tag', function ()
{
    expect(Seo::global()->robotsTag)->toContain('<meta name="robots" content="all"/>');
})->group('seo');

test('Get Global Social Tags', function ()
{
    expect(Seo::global()->social)->toContain('<meta');
})->group('seo');

test('Get Global Sitemap', function ()
{
    expect(Seo::global()->sitemap)->not->toBeEmpty();
})->group('seo');