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
        expect($result)->not->toBe('');
    } catch (Exception $e) {
        expect($result)->not->toBe('');
    }
});

test('Get Asset by name', function ()
{
    $item = Asset::getByName('unit_test.webp');
    expect($item)->not->toBeNull();
});

test('Asset (now webp) should have one transform', function ()
{
    $item = Asset::getByName('unit_test.webp');
    expect($item)->not->toBeNull()->and($item->transforms->length)->toBeGreaterThanOrEqual(1);
});

test('Create a new transform on the Asset', function ()
{
    $item = Asset::getByName('unit_test.webp');
    $result = '';

    try {
        $result = $item->transform('bigbox', 200, 200);
        expect($result)->not->toBe('');
    } catch (Exception $e) {
        expect($result)->not->toBe('');
    }
});

test('Delete Asset', function ()
{
    $item = Asset::getByName('unit_test.webp');

    if ($item) {
        $result = $item->remove();
        expect($result)->toBe(true);
    } else {
        expect(false)->toBe(true);
    }
});