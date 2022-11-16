<?php

use SailCMS\Security;

$phrase = 'Hello World!';

class EncryptionTest
{
    public static string $encrypted = '';
}

beforeAll(function ()
{
    Security::$overrideKey = sodium_crypto_aead_xchacha20poly1305_ietf_keygen();
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

test('Hash generation', function ()
{
    try {
        $hash = Security::hash('hello world!', true);

        expect($hash)->not->toBeEmpty();
    } catch (Exception $e) {
        expect(true)->toBeFalse();
    }
});

test('Hash verification', function ()
{
    try {
        $hash = Security::hash('hello world!', true);
        $verified = Security::valueMatchHash($hash, 'hello world!');

        expect($verified)->toBeTrue();
    } catch (Exception $e) {
        expect(true)->toBeFalse();
    }
});
