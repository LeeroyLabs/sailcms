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
        $model->createView('test', $model, [[
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
    } catch (Exception $e) {
        print_r($e);
        ob_flush();
        expect(false)->toBeTrue();
    }
})->group('model-view');