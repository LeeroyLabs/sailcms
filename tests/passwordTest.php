<?php

use SailCMS\Security;

beforeAll(function ()
{
    Security::$overrideKey = sodium_crypto_aead_xchacha20poly1305_ietf_keygen();
});

test('Fail verification with unsafe password without alpha/case/symbols', function ()
{
    $pass = Security::validatePassword('12345678', true);
    expect($pass)->toBeFalse();
});

test('Fail verification with unsafe password without case/symbols', function ()
{
    $pass = Security::validatePassword('dd12345678', true);
    expect($pass)->toBeFalse();
});

test('Fail verification with unsafe password without symbols', function ()
{
    $pass = Security::validatePassword('dD12345678', true);
    expect($pass)->toBeFalse();
});

test('Fail verification with unsafe password with length too short', function ()
{
    $pass = Security::validatePassword('dD12!', true);
    expect($pass)->toBeFalse();
});

test('Fail verification with unsafe password with length too long', function ()
{
    $pass = Security::validatePassword('44gh56thr66uhjefgjew846ofjkej4utiujHURGNURN383RGJNj38281hn1ghwhw!!!tw3hjusfh32@', true);
    expect($pass)->toBeFalse();
});

test('Pass verification with password with alphanum/case/symbols', function ()
{
    $pass = Security::validatePassword('Hell0World!', true);
    expect($pass)->toBeTrue();
});