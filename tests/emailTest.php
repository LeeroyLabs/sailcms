<?php

use SailCMS\Mail;
use SailCMS\Models\Email;
use SailCMS\Sail;

include_once __DIR__ . '/mock/db.php';
include_once __DIR__ . '/mock/email.php';

beforeAll(function ()
{
    Sail::setupForTests(__DIR__, 'tests/mock/templates');
});

test('Load "new_account" email settings', function ()
{
    $email = Email::getBySlug('new_account');
    expect($email)->not->toBeNull();
})->group('email');

test('Get list of emails', function ()
{
    $email = new Email();
    $list = $email->getList('default');

    expect($list->length)->toBeGreaterThanOrEqual(1);
})->group('email');

test('Create a new email called "test_email"', function ()
{
    $email = new Email();

    try {
        $result = $email->create(
            'test Email',
            ['fr' => 'Sujet', 'en' => 'Subject'],
            [
                ['key' => 'title', 'value' => ['fr' => '', 'en' => '']],
                ['key' => 'content', 'value' => ['fr' => '', 'en' => '']]
            ],
            'test_email'
        );

        expect($result)->toBeTrue();
    } catch (Exception $e) {
        expect(false)->toBeTrue();
    }
})->group('email');

test('Update email named "test_email"', function ()
{
    try {
        Email::updateBySlug(
            'test_email',
            'test Email Updated',
            ['fr' => 'Sujet', 'en' => 'Subject'],
            [
                ['key' => 'title', 'value' => ['fr' => '', 'en' => '']],
                ['key' => 'content', 'value' => ['fr' => '', 'en' => '']]
            ],
            'test_email'
        );

        $email = Email::getBySlug('test_email');

        expect($email)->not->toBeNull()->and($email->name)->toBe('test Email Updated');
    } catch (Exception $e) {
        expect(false)->toBeTrue();
    }
})->group('email');

test('Delete email named "test_email"', function ()
{
    try {
        $result = Email::removeBySlug('test_email');
        expect($result)->toBeTrue();
    } catch (Exception $e) {
        expect(true)->toBeFalse();
    }
})->group('email');

test('Send an email use templating', function ()
{
//    $mail = new Mail();
//    $mail->to('')->useEmail(
//        'new_system',
//        'en',
//        [
//            'replacements' => [],
//            'verification_code' => '111111111111',
//            'user_email' => ''
//        ]
//    )->send();
})->group('email2');