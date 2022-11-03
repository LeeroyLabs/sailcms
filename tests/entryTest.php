<?php

use SailCMS\Collection;
use SailCMS\Errors\EntryException;
use SailCMS\Models\Entry;
use SailCMS\Models\EntryType;
use SailCMS\Models\User;
use SailCMS\Sail;
use SailCMS\Types\EntryStatus;
use SailCMS\Types\Username;

beforeAll(function ()
{
    $_ENV['SITE_URL'] = 'http://localhost:8888';
    Sail::setAppState(Sail::STATE_CLI);

    $authorModel = new User();
    $username = new Username('Test', 'Entry', 'Test Entry');
    $userId = $authorModel->create($username, 'testentry@leeroy.ca', 'Hell0W0rld!', new Collection([]), '', null);
    User::$currentUser = $authorModel->getById($userId);
});

afterAll(function ()
{
    $authorModel = new User();
    $authorModel->removeByEmail('testentry@leeroy.ca');
});

test('Create an entry type', function ()
{
    $model = new EntryType();

    try {
        $id = $model->createOne('test', 'Test', 'test', null, false);
        expect($id)->not->toBe('');
    } catch (Exception $exception) {
        expect(true)->toBe(false);
    }
});

test('Failed to create an entry type because the handle is already in use', function ()
{
    $model = new EntryType();

    try {
        $model->createOne('test', 'Test', 'test', null, false);
        expect(true)->not->toBe(false);
    } catch (EntryException $exception) {
        expect($exception->getMessage())->toBe(EntryType::HANDLE_ALREADY_EXISTS);
    } catch (Exception $otherException) {
        expect(true)->toBe(false);
    }
});

test('Update an entry type', function ()
{
    $model = new EntryType();

    try {
        $result = $model->updateByHandle('test', new Collection([
            'title' => 'Test Pages',
            'url_prefix' => 'test-pages'
        ]));
        expect($result)->toBe(true);
        $entryType = $model->getByHandle('test');
        expect($entryType->title)->toBe('Test Pages');
        expect($entryType->url_prefix)->toBe('test-pages');
    } catch (Exception $exception) {
        expect(true)->toBe(false);
    }
});

test("Create an entry with the default type", function ()
{
    $model = new Entry();

    try {
        $entry = $model->createOne('fr', true, EntryStatus::LIVE, 'Home', null, []);
        expect($entry->title)->toBe('Home');
        expect($entry->status)->toBe(EntryStatus::LIVE->value);
        expect($entry->locale)->toBe('fr');
        expect($entry->slug)->toBe(null);
    } catch (Exception $exception) {
        expect(true)->toBe(false);
    }
});

// Fail to create a live entry because url already in use
// Fail to create a live entry because slug is empty and isHomepage is true
// Update a default entry
// Hard Delete a default entry

test('Soft Delete a default entry', function ()
{
    $model = new Entry();
    $entry = $model->one([
        'title' => 'Home'
    ]);

    try {
        $result = $entry->delete($entry->_id);
        $entry = $model->one([
            'title' => 'Home'
        ]);
        expect($result)->toBe(true);
        expect($entry->status)->toBe(EntryStatus::TRASH->value);
    } catch (EntryException $exception) {
        expect(true)->toBe(false);
    }
});

test('Hard Delete a default entry', function ()
{
    $model = new Entry();
    $entry = $model->one([
        'title' => 'Home'
    ]);

    try {
        $result = $entry->delete($entry->_id, false);
        expect($result)->toBe(true);
    } catch (EntryException $exception) {
        expect(true)->toBe(false);
    }
});

test('Delete an entry type', function ()
{
    $model = new EntryType();
    $entryType = $model->getByHandle('test');

    try {
        $result = $model->hardDelete($entryType->_id);
        expect($result)->toBe(true);
    } catch (EntryException $exception) {
        expect(true)->toBe(false);
    }
    $entryType = $model->getByHandle('test');
    expect($entryType)->toBe(null);
});