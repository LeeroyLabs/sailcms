<?php

use SailCMS\Text;

test('deburr', function ()
{
    expect(Text::from('ÀÉÔÊÉ')->deburr()->value())->toBe('AEOEE');
})->group('text');

test('kebabCase', function ()
{
    expect(Text::from('Hello World')->kebab()->value())->toBe('hello-world');
})->group('text');

test('slug', function ()
{
    expect(Text::from('Hello world of PHP!$#')->slug()->value())->toBe('hello-world-of-php');
})->group('text');

test('camel', function ()
{
    expect(Text::from('Hello world of PHP')->camel()->value())->toBe('helloWorldOfPHP');
})->group('text');

test('snake', function ()
{
    expect(Text::from('Hello world of PHP')->snake()->value())->toBe('hello_world_of_php');
})->group('text');

test('upper', function ()
{
    expect(Text::from('Hello world of PHP')->upper()->value())->toBe('HELLO WORLD OF PHP');
})->group('text');

test('lower', function ()
{
    expect(Text::from('Hello world of PHP')->lower()->value())->toBe('hello world of php');
})->group('text');

test('capitalize', function ()
{
    expect(Text::from('hello world')->capitalize()->value())->toBe('Hello World');
})->group('text');

test('capitalize first only', function ()
{
    expect(Text::from('hello world')->capitalize(true)->value())->toBe('Hello world');
})->group('text');

test('trim (both)', function ()
{
    expect(Text::from('  Hello World  ')->trim()->value())->toBe('Hello World');
})->group('text');

test('trim (left)', function ()
{
    expect(Text::from('  Hello World')->trimLeft()->value())->toBe('Hello World');
})->group('text');

test('trim (right)', function ()
{
    expect(Text::from('Hello World  ')->trimRight()->value())->toBe('Hello World');
})->group('text');

test('length', function ()
{
    expect(Text::from('Hello')->length)->toBe(5);
})->group('text');

test('substring', function ()
{
    expect(Text::from('Hello')->substr(0, 2)->value())->toBe('He');
})->group('text');

test('isEmail', function ()
{
    expect(Text::from('marc@leeroy.ca')->isEmail())->toBeTrue();
})->group('text');

test('isJSON', function ()
{
    expect(Text::from('{"test": 1}')->isJSON())->toBeTrue();
})->group('text');

test('isIP', function ()
{
    expect(Text::from('192.168.1.24')->isIP())->toBeTrue();
})->group('text');

test('isMacAddress', function ()
{
    expect(Text::from('00-B0-D0-63-C2-26')->isMacAddress())->toBeTrue();
})->group('text');

test('isMacAddress invalid', function ()
{
    expect(Text::from('00-B0-D0-63')->isMacAddress())->toBeFalse();
})->group('text');

test('isURL', function ()
{
    expect(Text::from('https://google.ca')->isURL())->toBeTrue();
})->group('text');

test('isURL invalid', function ()
{
    expect(Text::from('https://google')->isURL())->toBeFalse();
})->group('text');

test('isDomain', function ()
{
    expect(Text::from('www.google.ca')->isDomain())->toBeTrue();
})->group('text');

test('isDomain invalid', function ()
{
    expect(Text::from('google')->isDomain())->toBeFalse();
})->group('text');