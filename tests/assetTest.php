<?php

use SailCMS\Models\Asset;
use SailCMS\Sail;

include_once __DIR__ . '/mock/db.php';

beforeAll(function ()
{
    $_ENV['SITE_URL'] = 'http://localhost:8888';
    Sail::setWorkingDirectory(__DIR__ . '/mock');
    Sail::setAppState(Sail::STATE_CLI, 'dev', __DIR__ . '/mock');
});

test('Upload a jpg image and optimize to webp', function ()
{
    $asset = new Asset();
    $data = base64_decode(file_get_contents(__DIR__ . '/mock/asset/test.jpg.txt'));
    $result = '';

    try {
        $result = $asset->upload($data, 'unit_test.jpg');
        expect($result)->not->toBeEmpty();
    } catch (Exception $e) {
        expect($result)->not->toBeEmpty();
    }
})->group('assets');

test('Get Asset by name', function ()
{
    // The name is slugify when it's uploaded, so we must use the slug instead of the file name
//    $item = Asset::getByName('unit_test.webp');
    $item = Asset::getByName('unit-test-webp');
    expect($item)->not->toBeNull();
})->group('assets');

test('Asset (now webp) should have one transform', function ()
{
    $item = Asset::getByName('unit-test-webp');
    expect($item)->not->toBeNull()->and($item->transforms->length)->toBeGreaterThanOrEqual(1);
})->group('assets');

test('Create a new transform on the Asset', function ()
{
    $item = Asset::getByName('unit-test-webp');
    $result = '';

    try {
        $result = $item->transform('bigbox', 200, 200);
        expect($result)->not->toBeEmpty();
    } catch (Exception $e) {
        expect($result)->not->toBeEmpty();
    }
})->group('assets');

test('Delete Asset', function ()
{
    $item = Asset::getByName('unit-test-webp');

    if ($item) {
        $result = $item->remove();
        expect($result)->toBeTrue();
    } else {
        expect(false)->toBeTrue();
    }
})->group('assets');