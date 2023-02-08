<?php

include_once __DIR__ . '/mock/db.php';

use SailCMS\Sail;

beforeAll(function ()
{
    Sail::setupForTests(__DIR__);
});

test('Your test right here', function ()
{
    expect(true)->toBeTrue();
});