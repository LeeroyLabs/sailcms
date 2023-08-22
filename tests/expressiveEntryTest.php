<?php

use SailCMS\Models\EntryType;
use SailCMS\Sail;

beforeAll(function () {
    Sail::setupForTests(__DIR__);

    // Ensure default type exists
    EntryType::getDefaultType();
});

test('Test from method', function () {

})->group('expressive-entry');

test('Test withId method', function () {

})->group('expressive-entry');

test('Test isPublished method', function () {

})->group('expressive-entry');

test('Test parent method', function () {

})->group('expressive-entry');

test('Test alternate method', function () {

})->group('expressive-entry');

test('Test value method', function () {

})->group('expressive-entry');