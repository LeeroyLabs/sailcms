<?php

use SailCMS\Models\Config;

include_once __DIR__ . '/mock/db.php';

test('Set a config', function ()
{
    try {
        Config::setByName('testconf', ['test' => true]);
        expect(true)->toBeTrue();
    } catch (Exception $e) {
        expect(false)->toBeTrue();
    }
});

test('Update a config', function ()
{
    try {
        Config::setByName('testconf', ['test' => true]);
        $conf = Config::getByName('testconf');
        expect($conf)->not->toBeNull()->and(isset($conf->test))->toBeFalse();
    } catch (Exception $e) {
        echo $e->getMessage();
        expect(false)->toBeTrue();
    }
});

test('Read a config', function ()
{
    $conf = Config::getByName('testconf');

    expect($conf)->not->toBeNull()->and($conf->config->test)->toBeTrue();
});