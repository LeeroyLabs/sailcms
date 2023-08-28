<?php

use SailCMS\Entry;
use SailCMS\Models\EntryType;
use SailCMS\Sail;

beforeAll(function () {
    Sail::setupForTests(__DIR__);

    // Ensure default type exists
    EntryType::getDefaultType();

    $_ENV['entryId'] = "";

    // TODO create entry type

});

afterAll(function () {
    // TODO delete entry type
});

test('Test a page creation', function () {
    // Locale, title, template, site_id, isHomepage
    $qs = Entry::from()->create('en', 'Home', true);

    $_ENV['entryId'] = $qs->value()->_id;

    expect((string)$_ENV['entryId'])->toBeString();
})->group('expressive-entry');

test('Test to create a page publication', function () {
    // Publication date, expiration date
    $qs = Entry::from()->get($_ENV['entryId']);

    expect($qs->published())->toBeFalse()
        ->and($qs->publish(time())->published())->toBeTrue();
})->group('expressive-entry');

test('Test to update an entry', function () {
    $qs = Entry::from()->get($_ENV['entryId'])->update(['title' => 'Home - english']);

    expect($qs->updated())->toBeTrue()
        ->and($qs->value()->title)->toBe('Home - english');
});

//test('Test withId with parent method', function () {
//    $entryParent = Entry::from()->byId($this->entryDraftId)->parent();
//
//    expect($entryParent)->toBeInstanceOf(\SailCMS\Models\Entry::class);
//})->group('expressive-entry');

//
//test('Test withId with alternate method', function () {
//    $entryAlternate = Entry::from()->byId($this->entryDraftId)->alternate();
//})->group('expressive-entry');

test('Test delete with byId', function() {
    $result = Entry::from()->get($_ENV['entryId'])->delete();

    expect($result)->toBeTrue();
})->group('expressive-entry');