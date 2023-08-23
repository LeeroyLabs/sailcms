<?php

use SailCMS\Errors\StorageException;
use SailCMS\Sail;
use SailCMS\Storage;

include_once __DIR__ . '/mock/db.php';

beforeAll(function ()
{
    Sail::setupForTests(__DIR__);
});

test('Store a file on local disk', function ()
{
    $url = Storage::on('app')->store('test.txt', 'hello world of cosmetics :)')->url();
    expect($url)->not->toBeEmpty();
})->group('storage');

test('Read file', function ()
{
    $text = Storage::on('app')->read('test.txt')->raw();
    expect($text)->toBe('hello world of cosmetics :)');
})->group('storage');

test('Set permission on file', function ()
{
    $result = Storage::on('app')->setPermissions('test.txt', 'public');
    expect($result)->toBeTrue();
})->group('storage');

test('Copy file', function ()
{
    $result = Storage::on('app')->copy('test.txt', 'test2.txt', 'public');
    expect($result)->toBeTrue();
})->group('storage');

test('Move file', function ()
{
    $result = Storage::on('app')->move('test2.txt', 'test3.txt', 'public');
    expect($result)->toBeTrue();
})->group('storage');

test('File exists', function ()
{
    $result = Storage::on('app')->exists('test.txt');
    expect($result)->toBeTrue();
})->group('storage');

test('Create directory', function ()
{
    try {
        Storage::on('app')->createDirectory('testing');
        expect(true)->toBeTrue();
    } catch (StorageException $e) {
        expect(false)->toBeTrue();
    }
})->group('storage');

test('Delete directory', function ()
{
    try {
        Storage::on('app')->deleteDirectory('testing');
        expect(true)->toBeTrue();
    } catch (StorageException $e) {
        expect(false)->toBeTrue();
    }
})->group('storage');

test('Filesize', function ()
{
    $result = Storage::on('app')->size('test.txt');
    expect($result->bytes)->toBeGreaterThan(0);
})->group('storage');

test('Get mimetype', function ()
{
    $result = Storage::on('app')->mimetype('test.txt');
    expect($result)->toBe('text/plain');
})->group('storage');

test('Delete file', function ()
{
    try {
        Storage::on('app')->delete('test.txt');
        Storage::on('app')->delete('test3.txt');
        expect(true)->toBeTrue();
    } catch (StorageException $e) {
        expect(false)->toBeTrue();
    }
})->group('storage');