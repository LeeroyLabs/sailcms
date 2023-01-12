<?php

use SailCMS\Models\User;
use SailCMS\Sail;

beforeAll(function () {
    $_ENV['SITE_URL'] = 'http://localhost:8888';
    Sail::setWorkingDirectory(__DIR__ . '/mock');
    Sail::setAppState(Sail::STATE_CLI);
});

test('Model to JSON', function () {
    $model = new User();
    $user = $model->getById('63b6fdea07c72f7fc99c9e5f');

    expect($user->toJSON())->toContain('"roles":["general-user"]', '"name":{"first":"John","last":"Moe","full":"John Moe"}');
})->group('newdb')->skip();

test('Model JSON does not contain guarded value', function () {
    $model = new User();
    $user = $model->getById('63b6fdea07c72f7fc99c9e5f');

    expect($user->toJSON())->not->toContain('"password":');
})->group('newdb')->skip();

