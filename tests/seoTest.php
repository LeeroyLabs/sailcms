<?php

use SailCMS\Models\Redirection;
use SailCMS\Sail;

include_once __DIR__ . '/mock/db.php';

beforeAll(function () {
    Sail::setupForTests(__DIR__);
});

test('Create a redirection', function () {
    $redirection = Redirection::add(
        '/test',
        '/',
        '302'
    );
    expect($redirection)->toBeTrue();
})->group('seo');

test('Update a redirection', function () {
    $redirectionId = (new Redirection)->getByUrl('/test');

    $redirection = (new Redirection)->update(
        $redirectionId->id,
        '/test2',
        '/',
        '302'
    );
    expect($redirection)->toBeTrue();
})->group('seo');

test('Get a list of redirections', function () {
    $redirections = (new Redirection)->getList(
        1,
        15
    );
    expect($redirections)->not->toBeNull();
})->group('seo');


test('Delete a redirection', function () {
    $redirectionId = (new Redirection)->getByUrl('/test2');

    $redirection = (new Redirection)->delete($redirectionId->id);
    expect($redirection)->toBeTrue();
})->group('seo');