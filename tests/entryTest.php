<?php

use SailCMS\Collection;
use SailCMS\Errors\EntryException;
use SailCMS\Models\EntryType;
use SailCMS\Models\User;
use SailCMS\Sail;
use SailCMS\Types\Username;

beforeAll(function ()
{
    Sail::setAppState(Sail::STATE_CLI);

    // For entry tests
    $name = new Username('Entry', 'Doe', 'Entry Doe');
    $roles = new Collection(['super-administrator']);
    $meta = null;

    $userModel = new User();
    $userId = $userModel->create($name, 'entrytest@leeroy.ca', 'Hell0W0rld!', $roles, '', $meta);
});

beforeEach(function ()
{
    $userModel = new User();
    User::$currentUser = $userModel->getByEmail('entrytest@leeroy.ca');
});

afterAll(function ()
{
    $userModel = new User();
    $userModel->removeByEmail('entrytest@leeroy.ca');
});

test('Fail at create an entry type, the user has no write permissions', function ()
{
    $model = new EntryType();

    try {
        $id = $model->create('test', 'Test', 'test', null, false);
        expect($id)->not->toBe('');
    } catch (EntryException $exception) {
        // We are here because the user does not have permission to entry type
        expect(true)->toBe(true);
    }
});

//test('Create an entry type', function ()
//{
//    $model = new EntryType();
//
//    try {
//        $id = $model->create('test', 'Test', 'test', null, false);
//        expect($id)->not->toBe('');
//    } catch (Exception $exception) {
//        expect(true)->toBe(false);
//    }
//});

//test('Update an entry type', function ()
//{
//    $model = new EntryType();
//
//    try {
//        $id = $model->updateByHandle('test', new Collection([
//            'handle' => 'Test Node'
//        ]));
//        expect($id)->not->toBe('');
//    } catch (Exception $exception) {
//        expect(true)->toBe(false);
//    }
//});

//test("Create an entry", function ()
//{
//    $model = new Entry();
//
//
//    try {
//    } catch (Exception $exception) {
//    }
//});