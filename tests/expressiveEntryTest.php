<?php

use SailCMS\Entry;
use SailCMS\Models\EntryType;
use SailCMS\Sail;

beforeAll(function () {
    Sail::setupForTests(__DIR__);

    // Ensure default type exists
    EntryType::getDefaultType();

    $this->entryId = "";

    // TODO create entry type
});

afterAll(function () {
    // TODO delete entry type
});

test('Test a page creation', function () {
    // Locale, title, template, site_id, isHomepage
    Entry::from()->create();
});

test('Test to create a page publication', function () {
    // Publication date, expiration date
    Entry::from()->create()->publish();
});

test('Test isPublished method', function () {
    $entryPublished = Entry::from()->byId($this->entryId)->isPublished();

    expect($entryPublished)->toBeTrue();
})->group('expressive-entry');

//test('Test withId with parent method', function () {
//    $entryParent = Entry::from()->byId($this->entryDraftId)->parent();
//
//    expect($entryParent)->toBeInstanceOf(\SailCMS\Models\Entry::class);
//})->group('expressive-entry');
//
//test('Test withId with alternate method', function () {
//    $entryAlternate = Entry::from()->byId($this->entryDraftId)->alternate();
//})->group('expressive-entry');