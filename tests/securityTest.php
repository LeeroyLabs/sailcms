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

        expect($hash)->not->toBe('');
    } catch (Exception $e) {
        expect(true)->toBe(false);
    }
});

test('Hash verification', function ()
{
    try {
        $hash = Security::hash('hello world!', true);
        $verified = Security::valueMatchHash($hash, 'hello world!');

        expect($verified)->toBe(true);
    } catch (Exception $e) {
        expect(true)->toBe(false);
    }
});

// TODO: TEST PASSWORD
/*
 *
 *
 * $p1 = Security::validatePassword('12345678');
        $p2 = Security::validatePassword('123456');
        $p3 = Security::validatePassword('HelloWorld');
        $p4 = Security::validatePassword('helloworld');
        $p5 = Security::validatePassword('helloworld2');
        $p6 = Security::validatePassword('HelloWorld1');
 */
