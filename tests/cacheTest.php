<?php

use SailCMS\Cache;

include_once __DIR__ . '/mock/db.php';

beforeEach(function ()
{
    Cache::init();
});

test('Cache a scalar value', function ()
{
    Cache::set('testkey', 'hello world!');
    $value = Cache::get('testkey');

    expect($value)->toBe('hello world!');
});

test('Cache an object value', function ()
{
    Cache::set('objectkey', (object)['firstKey' => 'hello world!']);
    $value = Cache::get('objectkey');

    expect($value)->toBeObject();
});

test('Cache an array value', function ()
{
    Cache::set('arraykey', ['firstKey' => 'hello world!']);
    $value = Cache::get('arraykey');

    expect($value)->toBeArray();
});

test('Delete one key', function ()
{
    Cache::remove(['testkey']);
    $value = Cache::get('testkey');

    expect($value)->toBeNull();
});

test('Delete many keys', function ()
{
    Cache::remove(['objectkey', 'arraykey']);
    $value1 = Cache::get('objectkey');
    $value2 = Cache::get('arraykey');

    expect($value1)->toBeNull()->and($value2)->toBeNull();
});

test('Add many keys, delete all keys that start with "keypref_"', function ()
{
    Cache::set('keypref_testkey1', 'hello world!');
    Cache::set('keypref_testkey2', 'hello world!');
    Cache::set('keypref_testkey3', 'hello world!');
    Cache::set('testkey', 'hello world!');
    Cache::set('keypref_testkey4', 'hello world!');
    Cache::set('keypref_testkey5', 'hello world!');

    Cache::removeUsingPrefix('keypref_');

    $value1 = Cache::get('keypref_testkey5');
    $value2 = Cache::get('testkey');

    expect($value1)->toBeNull()->and($value2)->not->toBeNull();
});

test('Flush all', function ()
{
    Cache::removeAll();
    $value = Cache::get('testkey');

    expect($value)->toBeNull();
});