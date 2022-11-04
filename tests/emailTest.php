<?php

use SailCMS\Mail;
use SailCMS\Models\Email;

include_once __DIR__ . '/mock/db.php';
include_once __DIR__ . '/mock/email.php';

test('Load "new_account" email settings', function ()
{
    $email = Email::getBySlug('new_account');
    expect($email)->not->toBeNull();
});

test('Get list of emails', function ()
{
    $email = new Email();
    $list = $email->getList();

    expect($list->length)->toBeGreaterThanOrEqual(1);
});

test('Create a new email called "test_email"', function ()
{
    $email = new Email();

    try {
        $result = $email->create(
            'test Email',
            ['fr' => 'Sujet', 'en' => 'Subject'],
            ['fr' => 'Titre', 'en' => 'Title'],
            ['fr' => 'Content', 'en' => 'Contenu'],
            ['fr' => 'CTA', 'en' => 'CTA'],
            ['fr' => 'https://google.ca', 'en' => 'https://google.ca'],
            'test_email'
        );

        expect($result)->toBeTrue();
    } catch (Exception $e) {
        expect(false)->toBeTrue();
    }
});

test('Update email named "test_email"', function ()
{
    try {
        Email::updateBySlug(
            'test_email',
            'test Email Updated',
            ['fr' => 'Sujet1', 'en' => 'Subject1'],
            ['fr' => 'Titre', 'en' => 'Title'],
            ['fr' => 'Content', 'en' => 'Contenu'],
            ['fr' => 'CTA', 'en' => 'CTA'],
            ['fr' => 'https://google.ca', 'en' => 'https://google.ca'],
            'test_email'
        );

        $email = Email::getBySlug('test_email');

        expect($email)->not->toBeNull()->and($email->name)->toBe('test Email Updated');
    } catch (Exception $e) {
        expect(false)->toBeTrue();
    }
});

test('Delete email named "test_email"', function ()
{
    try {
        $result = Email::removeBySlug('test_email');
        expect($result)->toBeTrue();
    } catch (Exception $e) {
        expect(true)->toBeFalse();
    }
});