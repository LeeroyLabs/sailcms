<?php

use SailCMS\Models\Asset;
use SailCMS\Models\Config;
use SailCMS\Sail;

include_once __DIR__ . '/mock/db.php';

beforeAll(function ()
{
    Sail::setupForTests(__DIR__);
});

test('QuickUpdate using string field and boolean value', function ()
{
    $config = new Config();
    $config->quickUpdate('636e6d8c3fecb9ce3304a673', 'test', false);
    $data = Config::getByName('testconf');

    expect($data)->not->toBeNull()->and($data->test)->toBeFalse();
    $config->quickUpdate('636e6d8c3fecb9ce3304a673', 'test', true);
})->group('db');

test('QuickUpdate using array field and array value', function ()
{
    $config = new Config();
    $c = ['test' => true];

    $config->quickUpdate('636e6d8c3fecb9ce3304a673', ['test', 'config'], [false, $c]);
    $data = Config::getByName('testconf');

    expect($data)->not->toBeNull()->and($data->test)->toBeFalse()->and($data->config->test)->toBeTrue();

    $c = ['test' => false];
    $config->quickUpdate('636e6d8c3fecb9ce3304a673', ['test', 'config'], [true, $c]);
})->group('db');

test('QuickUpdate using array field but boolean value, expect failure', function ()
{
    $config = new Config();

    try {
        $config->quickUpdate('636e6d8c3fecb9ce3304a673', ['test', 'config'], false);
        expect(false)->toBeTrue();
    } catch (\SailCMS\Errors\DatabaseException $e) {
        // Should fail!
        expect(true)->toBeTrue();
    }
})->group('db');

test('Ensure object id for given array using ensureObjectIds', function ()
{
    $config = new Config();
    $ids = $config->ensureObjectIds(['6372a9d21a182a6c5f02a988', '6372a9d21a182a6c5f02a988', '6372a9d21a182a6c5f02a988']);

    expect($ids->length)->toBe(3)->and($ids->at(0))->toBeInstanceOf(\MongoDB\BSON\ObjectId::class);
})->group('db');