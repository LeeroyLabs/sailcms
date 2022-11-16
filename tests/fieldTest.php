<?php

use SailCMS\Sail;

beforeAll(function ()
{
    $_ENV['SITE_URL'] = 'http://localhost:8888';
    Sail::setAppState(Sail::STATE_CLI);
    // Create entry to test content
});

afterAll(function ()
{
    // Delete the test entry
});
