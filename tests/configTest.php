<?php

use SailCMS\Models\Config;
use SailCMS\Sail;

include_once __DIR__ . '/mock/db.php';

beforeAll(function ()
{
    $_ENV['SITE_URL'] = 'http://localhost:8888';
    Sail::setWorkingDirectory(__DIR__ . '/mock');
    Sail::setAppState(Sail::STATE_CLI);
});

test('Set a config', function ()
{
    try {
        Config::setByName('testconf', ['test' => true]);
        $conf = Config::getByName('testconf');
        expect($conf)->not->toBeNull();
    } catch (Exception $e) {
        expect(false)->toBeTrue();
    }
})->group('config');

test('Update a config', function ()
{
    try {
        Config::setByName('testconf', ['test' => false]);
        $conf = Config::getByName('testconf');

        expect($conf)->not->toBeNull()->and($conf->config->test)->toBeFalse();
    } catch (Exception $e) {
        echo $e->getMessage();
        expect(false)->toBeTrue();
    }
})->group('config');

test('Read a config', function ()
{
    $conf = Config::getByName('testconf');
    expect($conf)->not->toBeNull()->and($conf->config->test)->toBeFalse();
})->group('config');