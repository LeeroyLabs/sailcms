<?php

use SailCMS\Text;

test('isEmail', function ()
{
    expect(Text::from('marc@leeroy.ca')->isEmail())->toBeTrue();
})->group('text');

test('isEmail invalid', function ()
{
    expect(Text::from('marcleeroy.ca')->isEmail())->toBeFalse();
})->group('text');

test('isJSON', function ()
{
    expect(Text::from('{"test": 1}')->isJSON())->toBeTrue();
})->group('text');

test('isJSON invalid', function ()
{
    expect(Text::from('hello world')->isJSON())->toBeFalse();
})->group('text');

test('isIP', function ()
{
    expect(Text::from('192.168.1.24')->isIP())->toBeTrue();
})->group('text');

test('isIP invalid', function ()
{
    expect(Text::from('192.168.1.')->isIP())->toBeFalse();
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

test('isPostal', function ()
{
    expect(Text::from('J7R 6N4')->isPostal('ca'))->toBeTrue();
})->group('text');

test('isPostal invalid', function ()
{
    expect(Text::from('123456')->isPostal('ca'))->toBeFalse();
})->group('text');

test('isZip', function ()
{
    expect(Text::from('90210')->isZip())->toBeTrue();
})->group('text');

test('isZip invalid', function ()
{
    expect(Text::from('J7R 6N4')->isZip())->toBeFalse();
})->group('text');

test('startsWith insensitive', function ()
{
    expect(Text::from('Hello World')->startsWith('hell'))->toBeTrue();
})->group('text');

test('startsWith sensitive', function ()
{
    expect(Text::from('Hello World')->startsWith('Hell'))->toBeTrue();
})->group('text');

test('startsWith insensitive fail', function ()
{
    expect(Text::from('Hello World')->startsWith('hell!'))->toBeFalse();
})->group('text');

test('startsWith sensitive fail', function ()
{
    expect(Text::from('Hello World')->startsWith('hell'))->toBeTrue();
})->group('text');

test('endsWith insensitive', function ()
{
    expect(Text::from('Hello World')->endsWith('world'))->toBeTrue();
})->group('text');

test('endsWith sensitive', function ()
{
    expect(Text::from('Hello World')->endsWith('World', true))->toBeTrue();
})->group('text');

test('endsWith insensitive fail', function ()
{
    expect(Text::from('Hello World')->endsWith('orld!'))->toBeFalse();
})->group('text');

test('endsWith sensitive fail', function ()
{
    expect(Text::from('Hello World')->endsWith('world', true))->toBeFalse();
})->group('text');

test('contains insensitive', function ()
{
    expect(Text::from('Hello World')->contains('llo wo'))->toBeTrue();
})->group('text');

test('contains sensitive', function ()
{
    expect(Text::from('Hello World')->contains('llo Wo', true))->toBeTrue();
})->group('text');

test('contains insensitive fail', function ()
{
    expect(Text::from('Hello World')->contains('Man'))->toBeFalse();
})->group('text');

test('contains sensitive fail', function ()
{
    expect(Text::from('Hello World')->contains('lli wo', true))->toBeFalse();
})->group('text');