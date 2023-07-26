<?php

use SailCMS\Models\Entry;
use SailCMS\Models\EntryPublication;
use SailCMS\Sail;

beforeAll(function () {
    Sail::setupFogtirTests(__DIR__);
});

test('Create a view for entry and entry publication', function () {
    $model = new Entry();
    $modelPublication = new EntryPublication();

    try {
        $result = $model->createView('test', $model, [[
            '$lookup' => [
                'from' => $modelPublication->getCollection(),
                'let' => ['entryId' => '$_id'],
                'pipeline' => [
                    [
                        '$match' => [
                            '$expr' => [
                                '$eq' => [
                                    '$entry_id', [
                                        '$toString' => '$$entryId'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                'as' => 'entry_entry_publication'
            ]
        ]]);
        \SailCMS\Debug::ray($result);
    } catch (Exception $e) {
        \SailCMS\Debug::ray($e);
        expect(false)->toBeTrue();
    }
})->group('model-view');

test('Delete a view for entry and entry publication', function () {
    $model = new Entry();

    try {
        $result = $model->deleteView('test');
        \SailCMS\Debug::ray($result);
    } catch (Exception $e) {
        \SailCMS\Debug::ray($e);
        expect(false)->toBeTrue();
    }
})->group('model-view');