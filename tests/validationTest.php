<?php

use SailCMS\Collection;
use SailCMS\Models\EntryField;
use SailCMS\Sail;
use SailCMS\Types\LocaleField;
use SailCMS\Validator;

beforeAll(function () {
    Sail::setupForTests(__DIR__);

    (new EntryField())->create(new Collection([
        'key' => 'validationtest',
        'name' => 'Test',
        'label' => new LocaleField(['en' => 'Test', 'fr' => 'Test']),
        'type' => 'text',
        'required' => true,
        'validation' => ''
    ]));
});

afterAll(function () {
    (new EntryField())->deleteByIdOrKey(null, 'validationtest');
});


test('Empty validation', function () {
    $entryField = EntryField::getByKey('validationtest');

    expect(Validator::validateContentWithEntryField("whatever value", $entryField)->length)->toBe(0);
})->group('validation');

test("Boolean validation with an entry field", function () {
    $entryField = EntryField::getByKey('validationtest');
    (new EntryField())->update($entryField->_id, new Collection([
        'validation' => 'boolean'
    ]));
    $entryField = EntryField::getByKey('validationtest');

    expect(Validator::validateContentWithEntryField(true, $entryField)->length)->toBe(0)
        ->and(Validator::validateContentWithEntryField('not a bool', $entryField)->length)->toBe(1);
})->group('validation');

test("Email validation with an entry field", function () {
    $entryField = EntryField::getByKey('validationtest');
    (new EntryField())->update($entryField->_id, new Collection([
        'validation' => 'email'
    ]));
    $entryField = EntryField::getByKey('validationtest');

    expect(Validator::validateContentWithEntryField("philippe@leeroy.ca", $entryField)->length)->toBe(0)
        ->and(Validator::validateContentWithEntryField('false', $entryField)->length)->toBe(1);
})->group('validation');

test("Repeatable validation with an entry field", function () {
    $entryField = EntryField::getByKey('validationtest');

    expect(Validator::validateContentWithEntryField(["philippe@leeroy.ca", "good@address.ca"], $entryField)->length)->toBe(0)
        ->and(Validator::validateContentWithEntryField(["philippe@leeroy.ca", "badaddress.ca"], $entryField)[1][0])->toBe(sprintf(Validator::VALIDATION_FAILED, 'email'));
})->group('validation');

test("Url validation with an entry field", function () {
    $entryField = EntryField::getByKey('validationtest');
    (new EntryField())->update($entryField->_id, new Collection([
        'validation' => 'url'
    ]));
    $entryField = EntryField::getByKey('validationtest');

    expect(Validator::validateContentWithEntryField("https://domain.test/url/is/ok", $entryField)->length)->toBe(0)
        ->and(Validator::validateContentWithEntryField('this/is/false', $entryField)->length)->toBe(1);
})->group('validation');

test("Domain validation with an entry field", function () {
    $entryField = EntryField::getByKey('validationtest');
    (new EntryField())->update($entryField->_id, new Collection([
        'validation' => 'domain',
        'config' => ['tld' => false]
    ]));
    $entryField = EntryField::getByKey('validationtest');

    expect(Validator::validateContentWithEntryField("domain.test", $entryField)->length)->toBe(0)
        ->and(Validator::validateContentWithEntryField('false', $entryField)->length)->toBe(1);
})->group('validation');

test("IP validation with an entry field", function () {
    $entryField = EntryField::getByKey('validationtest');
    (new EntryField())->update($entryField->_id, new Collection([
        'validation' => 'ip',
        'config' => null
    ]));
    $entryField = EntryField::getByKey('validationtest');

    expect(Validator::validateContentWithEntryField("0.0.0.0", $entryField)->length)->toBe(0)
        ->and(Validator::validateContentWithEntryField('false', $entryField)->length)->toBe(1);
})->group('validation');

test("Integer validation with an entry field", function () {
    $entryField = EntryField::getByKey('validationtest');
    (new EntryField())->update($entryField->_id, new Collection([
        'validation' => 'integer',
        'config' => null
    ]));
    $entryField = EntryField::getByKey('validationtest');

    expect(Validator::validateContentWithEntryField(101, $entryField)->length)->toBe(0)
        ->and(Validator::validateContentWithEntryField('not a number', $entryField)->length)->toBe(1);
})->group('validation');

test("Float validation with an entry field", function () {
    $entryField = EntryField::getByKey('validationtest');
    (new EntryField())->update($entryField->_id, new Collection([
        'validation' => 'float',
        'config' => null
    ]));
    $entryField = EntryField::getByKey('validationtest');

    expect(Validator::validateContentWithEntryField(10.2, $entryField)->length)->toBe(0)
        ->and(Validator::validateContentWithEntryField('not a float', $entryField)->length)->toBe(1);
})->group('validation');

test("Numeric validation with an entry field", function () {
    $entryField = EntryField::getByKey('validationtest');
    (new EntryField())->update($entryField->_id, new Collection([
        'validation' => 'numeric',
        'config' => null
    ]));
    $entryField = EntryField::getByKey('validationtest');

    expect(Validator::validateContentWithEntryField('10.2', $entryField)->length)->toBe(0)
        ->and(Validator::validateContentWithEntryField('not numeric', $entryField)->length)->toBe(1);
})->group('validation');

test("Alpha validation with an entry field", function () {
    $entryField = EntryField::getByKey('validationtest');
    (new EntryField())->update($entryField->_id, new Collection([
        'validation' => 'alpha',
        'config' => ['extraChars' => ['-', ' ']]
    ]));
    $entryField = EntryField::getByKey('validationtest');

    expect(Validator::validateContentWithEntryField('alpha value plus -', $entryField)->length)->toBe(0)
        ->and(Validator::validateContentWithEntryField('0001-0001', $entryField)->length)->toBe(1);
})->group('validation');

test("Alphanum validation with an entry field", function () {
    $entryField = EntryField::getByKey('validationtest');
    (new EntryField())->update($entryField->_id, new Collection([
        'validation' => 'alphanum',
        'config' => ['extraChars' => ['-', ' ']]
    ]));
    $entryField = EntryField::getByKey('validationtest');

    expect(Validator::validateContentWithEntryField('200 - Alphanum value plus -', $entryField)->length)->toBe(0)
        ->and(Validator::validateContentWithEntryField('500 - Not allowed!', $entryField)->length)->toBe(1);
})->group('validation');

test("Min validation with an entry field", function () {
    $entryField = EntryField::getByKey('validationtest');
    (new EntryField())->update($entryField->_id, new Collection([
        'validation' => 'min',
        'config' => ['min' => -10]
    ]));
    $entryField = EntryField::getByKey('validationtest');

    expect(Validator::validateContentWithEntryField('-10', $entryField)->length)->toBe(0)
        ->and(Validator::validateContentWithEntryField(-11, $entryField)->length)->toBe(1);
})->group('validation');

test("Max validation with an entry field", function () {
    $entryField = EntryField::getByKey('validationtest');
    (new EntryField())->update($entryField->_id, new Collection([
        'validation' => 'max',
        'config' => ['max' => -10]
    ]));
    $entryField = EntryField::getByKey('validationtest');

    expect(Validator::validateContentWithEntryField(-10.1, $entryField)->length)->toBe(0)
        ->and(Validator::validateContentWithEntryField(101, $entryField)->length)->toBe(1);
})->group('validation');

test("Between validation with an entry field", function () {
    $entryField = EntryField::getByKey('validationtest');
    (new EntryField())->update($entryField->_id, new Collection([
        'validation' => 'between',
        'config' => ['min' => -10, 'max' => 10]
    ]));
    $entryField = EntryField::getByKey('validationtest');

    expect(Validator::validateContentWithEntryField(2, $entryField)->length)->toBe(0)
        ->and(Validator::validateContentWithEntryField(-INF, $entryField)->length)->toBe(1);
})->group('validation');

test("ID validation with an entry field", function () {
    $entryField = EntryField::getByKey('validationtest');
    (new EntryField())->update($entryField->_id, new Collection([
        'validation' => 'id',
        'config' => null
    ]));
    $entryField = EntryField::getByKey('validationtest');

    expect(Validator::validateContentWithEntryField("64b0258a02e57cf28d0e1f22", $entryField)->length)->toBe(0)
        ->and(Validator::validateContentWithEntryField("64b0258a02e57cf28d0e1f2", $entryField)->length)->toBe(1);
})->group('validation');

test("Hex color validation with an entry field", function () {
    $entryField = EntryField::getByKey('validationtest');
    (new EntryField())->update($entryField->_id, new Collection([
        'validation' => 'hexColor',
        'config' => null
    ]));
    $entryField = EntryField::getByKey('validationtest');

    expect(Validator::validateContentWithEntryField("b34410", $entryField)->length)->toBe(0)
        ->and(Validator::validateContentWithEntryField("wswsws", $entryField)->length)->toBe(1);
})->group('validation');

test("Directory validation with an entry field", function () {
    $entryField = EntryField::getByKey('validationtest');
    (new EntryField())->update($entryField->_id, new Collection([
        'validation' => 'directory',
        'config' => null
    ]));
    $entryField = EntryField::getByKey('validationtest');

    expect(Validator::validateContentWithEntryField(__DIR__, $entryField)->length)->toBe(0)
        ->and(Validator::validateContentWithEntryField("not a dir", $entryField)->length)->toBe(1);
})->group('validation');

test("File validation with an entry field", function () {
    $entryField = EntryField::getByKey('validationtest');
    (new EntryField())->update($entryField->_id, new Collection([
        'validation' => 'file',
        'config' => null
    ]));
    $entryField = EntryField::getByKey('validationtest');

    expect(Validator::validateContentWithEntryField(__FILE__, $entryField)->length)->toBe(0)
        ->and(Validator::validateContentWithEntryField("not a file", $entryField)->length)->toBe(1);
})->group('validation');

test("Postal code validation with an entry field", function () {
    $entryField = EntryField::getByKey('validationtest');
    (new EntryField())->update($entryField->_id, new Collection([
        'validation' => 'postalCode',
        'config' => null
    ]));
    $entryField = EntryField::getByKey('validationtest');

    expect(Validator::validateContentWithEntryField('H1V 1V1', $entryField)->length)->toBe(0)
        ->and(Validator::validateContentWithEntryField("not a PC", $entryField)->length)->toBe(1);
})->group('validation');

test("Zip code validation with an entry field", function () {
    $entryField = EntryField::getByKey('validationtest');
    (new EntryField())->update($entryField->_id, new Collection([
        'validation' => 'zip',
        'config' => null
    ]));
    $entryField = EntryField::getByKey('validationtest');

    expect(Validator::validateContentWithEntryField('20001', $entryField)->length)->toBe(0)
        ->and(Validator::validateContentWithEntryField("not a zip", $entryField)->length)->toBe(1);
})->group('validation');

test("Postal (with country list) validation with an entry field", function () {
    $entryField = EntryField::getByKey('validationtest');
    (new EntryField())->update($entryField->_id, new Collection([
        'validation' => 'postal',
        'config' => ['country' => ['US', 'IN']]
    ]));
    $entryField = EntryField::getByKey('validationtest');

    expect(Validator::validateContentWithEntryField('127308', $entryField)->length)->toBe(0)
        ->and(Validator::validateContentWithEntryField("H1V 1V1", $entryField)->length)->toBe(1);
})->group('validation');

test("Country code validation with an entry field", function () {
    $entryField = EntryField::getByKey('validationtest');
    (new EntryField())->update($entryField->_id, new Collection([
        'validation' => 'countryCode',
        'config' => null
    ]));
    $entryField = EntryField::getByKey('validationtest');

    expect(Validator::validateContentWithEntryField('CA', $entryField)->length)->toBe(0)
        ->and(Validator::validateContentWithEntryField("not a country code", $entryField)->length)->toBe(1);
})->group('validation');

test("Phone validation with an entry field", function () {
    $entryField = EntryField::getByKey('validationtest');
    (new EntryField())->update($entryField->_id, new Collection([
        'validation' => 'phone',
        'config' => ['country' => 'CA']
    ]));
    $entryField = EntryField::getByKey('validationtest');

    expect(Validator::validateContentWithEntryField('15145145145', $entryField)->length)->toBe(0)
        ->and(Validator::validateContentWithEntryField("1234567890", $entryField)->length)->toBe(1);
})->group('validation');

test("Date validation with an entry field", function () {
    $entryField = EntryField::getByKey('validationtest');
    (new EntryField())->update($entryField->_id, new Collection([
        'validation' => 'date',
        'config' => ['format' => 'Y-m']
    ]));
    $entryField = EntryField::getByKey('validationtest');

    expect(Validator::validateContentWithEntryField('2023-10', $entryField)->length)->toBe(0)
        ->and(Validator::validateContentWithEntryField("2023-10-10", $entryField)->length)->toBe(1);
})->group('validation');

test("Time validation with an entry field", function () {
    $entryField = EntryField::getByKey('validationtest');
    (new EntryField())->update($entryField->_id, new Collection([
        'validation' => 'time',
        'config' => ['format' => 'H:i']
    ]));
    $entryField = EntryField::getByKey('validationtest');

    expect(Validator::validateContentWithEntryField('23:10', $entryField)->length)->toBe(0)
        ->and(Validator::validateContentWithEntryField("23:20:20", $entryField)->length)->toBe(1);
})->group('validation');

test("Datetime validation with an entry field", function () {
    $entryField = EntryField::getByKey('validationtest');
    (new EntryField())->update($entryField->_id, new Collection([
        'validation' => 'datetime',
        'config' => null
    ]));
    $entryField = EntryField::getByKey('validationtest');

    expect(Validator::validateContentWithEntryField('2023-10-20 23:10', $entryField)->length)->toBe(0)
        ->and(Validator::validateContentWithEntryField("2023-10 23:20:20", $entryField)->length)->toBe(1);
})->group('validation');

test("Credit card validation with an entry field", function () {
    $entryField = EntryField::getByKey('validationtest');
    (new EntryField())->update($entryField->_id, new Collection([
        'validation' => 'creditcard',
        'config' => ['cardBrand' => \Respect\Validation\Rules\CreditCard::VISA]
    ]));
    $entryField = EntryField::getByKey('validationtest');

    expect(Validator::validateContentWithEntryField('4242424242424242', $entryField)->length)->toBe(0)
        ->and(Validator::validateContentWithEntryField("2023-10 23:20:20", $entryField)->length)->toBe(1);
})->group('validation');

test("Uuid validation with an entry field", function () {
    $entryField = EntryField::getByKey('validationtest');
    (new EntryField())->update($entryField->_id, new Collection([
        'validation' => 'uuid',
        'config' => ['version' => 1]
    ]));
    $entryField = EntryField::getByKey('validationtest');

    expect(Validator::validateContentWithEntryField('428478f6-21ae-11ee-be56-0242ac120002', $entryField)->length)->toBe(0)
        ->and(Validator::validateContentWithEntryField('eb3115e5-bd16-4939-ab12-2b95745a30f3', $entryField)->length)->toBe(1);
})->group('validation');

test("Multiple validation with an entry field", function () {
    $entryField = EntryField::getByKey('validationtest');
    (new EntryField())->update($entryField->_id, new Collection([
        'validation' => 'uuid|email',
        'config' => ['version' => 1]
    ]));
    $entryField = EntryField::getByKey('validationtest');

    expect(Validator::validateContentWithEntryField('428478f6-21ae-11ee-be56-0242ac120002', $entryField)->length)->toBe(0)
        ->and(Validator::validateContentWithEntryField('philippe@leeroy.ca', $entryField)->length)->toBe(0)
        ->and(Validator::validateContentWithEntryField('eb3115e5-bd16-4939-ab12-2b95745a30f3', $entryField)->length)->toBe(2);
})->group('validation');