<?php

use SailCMS\Models\Entry;
use SailCMS\Models\EntryPublication;
use SailCMS\Sail;

beforeAll(function () {
    Sail::setupForTests(__DIR__);
});

test('Create a view for entry and entry publication', function () {
    $model = new Entry();
    $modelPublication = new EntryPublication();

    try {
        $result = $model->createView('test', [[
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
        expect($result)->toBeTrue();
    } catch (Exception $e) {
        expect(false)->toBeTrue();
    }
})->group('model-view');

test('Delete a view for entry and entry publication', function () {
    $model = new Entry();

    try {
        $result = $model->deleteTestView();
        expect($result)->toBeTrue();
    } catch (Exception $e) {
        \SailCMS\Debug::ray($e);
        expect(false)->toBeTrue();
    }
})->group('model-view');