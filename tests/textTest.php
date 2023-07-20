<?php

use SailCMS\Sail;
use SailCMS\Text;

beforeAll(function ()
{
    Sail::setupForTests(__DIR__);
});

test('length', function ()
{
    expect(Text::from('Hello')->length)->toBe(5);
})->group('text');

test('substring', function ()
{
    expect(Text::from('Hello')->substr(0, 2)->value())->toBe('He');
})->group('text');

test('uuid v4', function ()
{
    expect(Text::from('')->uuid(4)->length)->toBe(36);
})->group('text');

test('uuid v5', function ()
{
    expect(Text::from('')->uuid(5, 'ba36d08b-0d1c-45e6-99d2-e7efe7d51381', 'name')->length)->toBe(36);
})->group('text');

test('random', function ()
{
    expect(Text::from('')->random(10)->length)->toBe(10);
})->group('text');