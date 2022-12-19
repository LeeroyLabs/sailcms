<?php

use SailCMS\Cache;
use SailCMS\Models\Config;

include_once __DIR__ . '/mock/db.php';

beforeEach(function () {
    Cache::init();
});

test('Cache a database model and retrieve it', function () {
    Config::setByName('cachetester', ['test' => 1]);
    $t1 = Config::getByName('cachetester');

    expect($t1)->not->toBeNull();
});