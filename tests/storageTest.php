<?php

use SailCMS\Cosmetics\Storage;
use SailCMS\Sail;

include_once __DIR__ . '/mock/db.php';

beforeAll(function ()
{
    Sail::setupForTests(__DIR__);
});

test('Store a file on local disk', function ()
{
    $url = Storage::on('app')->store('test.txt', 'hello world of cosmetics :)')->url();

    \SailCMS\Debug::ray($url);
    expect($url)->not->toBeEmpty();
})->group('storage');