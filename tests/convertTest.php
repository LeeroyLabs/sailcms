<?php

use SailCMS\Convert;
use SailCMS\Models\Asset;
use SailCMS\Sail;

include_once __DIR__ . '/mock/db.php';

beforeAll(function ()
{
    Sail::setupForTests(__DIR__);
});

test('csv2array', function ()
{
    $csv = file_get_contents(__DIR__ . '/mock/fixtures/test.csv');
    $value = Convert::csv2array($csv);
    expect($value[0])->toBeArray();
})->group('convert');

test('csv2object', function ()
{
    $csv = file_get_contents(__DIR__ . '/mock/fixtures/test.csv');
    $value = Convert::csv2object($csv);
    expect($value[0])->toBeObject();
})->group('convert');

test('toCSV', function ()
{
    $data = include __DIR__ . '/mock/fixtures/data.php';
    $value = Convert::toCSV($data);
    expect($value)->toBeString()->and($value)->not->toBeEmpty();
})->group('convert');

test('csv2html', function ()
{
    $csv = file_get_contents(__DIR__ . '/mock/fixtures/test.csv');
    $value = Convert::csv2html($csv, ['name', 'email', 'value']);
    expect($value)->toBeString()->and($value)->not->toBeEmpty();
})->group('convert');