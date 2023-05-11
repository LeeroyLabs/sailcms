<?php

use SailCMS\Sail;
use SailCMS\Text;

class EncryptTest
{
    public static string $enc;
}

beforeAll(function ()
{
    Sail::setupForTests(__DIR__);
});

test('hash', function ()
{
    expect(Text::from('Hello World')->hash('sha256'))->toBe('a591a6d40bf420404a011733cfb7b190d62c65bf0bcda32b57b277d9ad9f146e');
})->group('text');

test('sha1', function ()
{
    expect(Text::from('Hello World')->sha1())->toBe('0a4d55a8d778e5022fab701977c5d840bbc486d0');
})->group('text');

test('sha256', function ()
{
    expect(Text::from('Hello World')->sha256())->toBe('a591a6d40bf420404a011733cfb7b190d62c65bf0bcda32b57b277d9ad9f146e');
})->group('text');

test('sha512', function ()
{
    expect(Text::from('Hello World')->sha512())->toBe('2c74fd17edafd80e8447b0d46741ee243b7eb74dd2149a0ab1b9246fb30382f27e853d8585719e0e67cbda0daa8f51671064615d645ae27acb15bfb1447f459b');
})->group('text');

test('md5', function ()
{
    expect(Text::from('Hello World')->md5())->toBe('b10a8db164e0754105b7a99be72e3fe5');
})->group('text');

test('crc32', function ()
{
    expect(Text::from('Hello World')->crc32())->toBe('1243066710');
})->group('text');

test('encrypt', function ()
{
    EncryptTest::$enc = Text::from('Hello World')->encrypt();
    expect(EncryptTest::$enc)->toContain('.');
})->group('text');

test('decrypt', function ()
{
    expect(Text::from(EncryptTest::$enc)->decrypt())->toBe('Hello World');
})->group('text');

test('encode', function ()
{
    expect(Text::from('Hello World')->encode())->toBe('SGVsbG8gV29ybGQ=');
})->group('text');

test('decode', function ()
{
    expect(Text::from('SGVsbG8gV29ybGQ=')->decode())->toBe('Hello World');
})->group('text');