<?php

use SailCMS\Entry;
use SailCMS\Models\EntryType;
use SailCMS\Sail;

beforeAll(function () {
    Sail::setupForTests(__DIR__);

    // Ensure default type exists
    EntryType::getDefaultType();

    $this->entryPublishedId = "";
    $this->entryDraftId = "";
});

test('Test withId with isPublished method', function () {
    $entryPublished = Entry::from()->withId($this->entryPublishedId)->isPublished();
    $entryNotPublished = Entry::from()->withId($this->entryDraftId)->isPublished();

    expect($entryPublished)->toBeTrue();
    expect($entryNotPublished)->toBeFalse();
})->group('expressive-entry');

test('Test withId with parent method', function () {
    $entryParent = Entry::from()->withId($this->entryDraftId)->parent();

    expect($entryParent)->toBeInstanceOf(\SailCMS\Models\Entry::class);
})->group('expressive-entry');

test('Test withId with alternate method', function () {

})->group('expressive-entry');

test('Test withId with value method', function () {

})->group('expressive-entry');