<?php

use SailCMS\Collection;
use SailCMS\Errors\EntryException;
use SailCMS\Models\EntryType;
use SailCMS\Sail;

beforeEach(function ()
{
    Sail::setAppState(Sail::STATE_CLI);
});

test('Create an entry type', function ()
{
    $model = new EntryType();

    try {
        $id = $model->create('test', 'Test', 'test', null, false);
        expect($id)->not->toBe('');
    } catch (Exception $exception) {
        expect(true)->toBe(false);
    }
});

test('Failed to create an entry type because the handle is already in use', function ()
{
    $model = new EntryType();

    try {
        $model->create('test', 'Test', 'test', null, false);
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

//test("Create an entry", function ()
//{
//    $model = new Entry();
//
//
//    try {
//    } catch (Exception $exception) {
//    }
//});

test('Delete an entry type', function ()
{
    $model = new EntryType();
    $entryType = $model->getByHandle('test');

    print_r($entryType);

    try {
        $result = $model->hardDelete($entryType->_id);
        expect($result)->toBe(true);
    } catch (EntryException $exception) {
        expect(true)->toBe(false);
    }
    $entryType = $model->getByHandle('test');
    expect($entryType)->toBe(null);
});