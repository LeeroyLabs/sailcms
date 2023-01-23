<?php

include_once __DIR__ . '/mock/db.php';

use SailCMS\Sail;

beforeAll(function ()
{
    $_ENV['SITE_URL'] = 'https://site.com/'; // Does not matter really
    Sail::setWorkingDirectory(__DIR__ . '/mock');
    Sail::setAppState(Sail::STATE_CLI);
});

test('Your test right here', function ()
{
    expect(true)->toBeTrue();
});