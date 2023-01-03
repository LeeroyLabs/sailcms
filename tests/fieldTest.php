<?php

use SailCMS\Collection;
use SailCMS\Models\Entry\NumberField;
use SailCMS\Models\Entry\TextField;
use SailCMS\Models\EntryLayout;
use SailCMS\Models\EntryType;
use SailCMS\Models\User;
use SailCMS\Sail;
use SailCMS\Types\EntryStatus;
use SailCMS\Types\Fields\Field as InputField;
use SailCMS\Types\Fields\InputNumberField;
use SailCMS\Types\LocaleField;
use SailCMS\Types\Username;

beforeAll(function () {
    $_ENV['SITE_URL'] = 'http://localhost:8888';
    Sail::setAppState(Sail::STATE_CLI);

    $authorModel = new User();
    $username = new Username('Test', 'Entry', 'Test Entry');
    $userId = $authorModel->create($username, 'testentryfield@leeroy.ca', 'Hell0W0rld!', Collection::init());
    User::$currentUser = $authorModel->getById($userId);

    $layoutModel = new EntryLayout();
    $entryLayout = $layoutModel->create(new LocaleField(['fr' => 'Test des champs', 'en' => 'Field Test']), Collection::init());

    $entryType = (new EntryType)->create('field-test', 'Field Test', new LocaleField(['en' => 'field-test', 'fr' => 'test-de-champs']), $entryLayout->_id);

    $entryType->getEntryModel($entryType)->create(false, 'fr', EntryStatus::LIVE, 'Home Field Test', 'page');
});

afterAll(function () {
    $authorModel = new User();
    $authorModel->removeByEmail('testentryfield@leeroy.ca');

    $entryModel = EntryType::getEntryModelByHandle('field-test');
    $entry = $entryModel->one([
        'title' => 'Home Field Test'
    ]);
    $entryModel->delete($entry->_id, false);

    (new EntryType)->hardDelete($entry->entry_type_id);

    $layoutModel = new EntryLayout();
    $entryLayout = $layoutModel->one([
        'titles.fr' => 'Test des champs'
    ]);
    $layoutModel->delete($entryLayout->_id, false);
});

test('Add all fields to the layout', function () {
    $layoutModel = new EntryLayout();
    $entryLayout = $layoutModel->one([
        'titles.fr' => 'Test des champs'
    ]);

    // Field with default settings
    $textField = new TextField(new LocaleField(['en' => 'Text', 'fr' => 'Texte']), [
        [
            'required' => true,
            'max_length' => 10,
            'min_length' => 5
        ]
    ]);
    $phoneField = new TextField(new LocaleField(['en' => 'Phone', 'fr' => 'Téléphone']), [
        [
            'pattern' => "\d{3}-\d{3}-\d{4}"
        ]
    ]);
    $numberFieldInteger = new NumberField(new LocaleField(['en' => 'Integer', 'fr' => 'Entier']), [
        [
            'min' => -1,
            'max' => 11
        ]
    ]);
    $numberFieldFloat = new NumberField(new LocaleField(['en' => 'Float', 'fr' => 'Flottant']), [
        [
            'required' => true,
            'min' => 0.03
        ]
    ], 2);

    $fields = new Collection([
        "text" => $textField,
        "phone" => $phoneField,
        "integer" => $numberFieldInteger,
        "float" => $numberFieldFloat
    ]);

    $schema = EntryLayout::generateLayoutSchema($fields);

    try {
        $updated = (new EntryLayout())->updateById($entryLayout->_id, $entryLayout->titles, $schema);
        expect($updated)->toBe(true);
    } catch (Exception $exception) {
        expect(true)->toBe(false);
    }
});

test('Failed to update the entry content', function () {
    $entryModel = EntryType::getEntryModelByHandle('field-test');
    $entry = $entryModel->one([
        'title' => 'Home Field Test'
    ]);

    try {
        $errors = $entryModel->updateById($entry, [
            'content' => [
                'float' => '0'
            ]
        ], false);
        expect($errors->length)->toBeGreaterThan(0);
        expect($errors->get('text')[0][0])->toBe(InputField::FIELD_REQUIRED);
        expect($errors->get('float')[0][0])->toBe(sprintf(InputNumberField::FIELD_TOO_SMALL, '0.03'));
    } catch (Exception $exception) {
//        print_r($exception->getMessage());
        expect(true)->toBe(false);
    }
});

test('Update content with success', function () {
    $entryModel = EntryType::getEntryModelByHandle('field-test');
    $entry = $entryModel->one([
        'title' => 'Home Field Test'
    ]);

    try {
        $errors = $entryModel->updateById($entry, [
            'content' => [
                'float' => '0.03',
                'text' => 'Not empty'
            ]
        ], false);
        expect($errors->length)->toBe(0);
    } catch (Exception $exception) {
//        print_r($exception->getMessage());
        expect(true)->toBe(false);
    }
});