<?php

use SailCMS\Filesystem;
use SailCMS\Security;

$phrase = 'Hello World!';

class EncryptionTest
{
    public static string $encrypted = '';
}

beforeAll(function ()
{
    Filesystem::mountCore(__DIR__ . '/mock/fs');
    Filesystem::init();

    Security::init();
    Security::loadSettings([]);
});

test('Encrypt', function () use ($phrase)
{
    $enc = Security::encrypt($phrase);
    EncryptionTest::$encrypted = $enc;
    $length = strlen($enc);
    expect($enc)->toContain('.')->and($length)->toBeGreaterThan(20);
});

test('Decrypt', function () use ($phrase)
{
    expect(Security::decrypt(EncryptionTest::$encrypted))->toBe($phrase);
});