<?php

use SailCMS\Collection;
use SailCMS\Errors\EntryException;
use SailCMS\Models\Entry;
use SailCMS\Models\Entry\TextField;
use SailCMS\Models\EntryLayout;
use SailCMS\Models\EntryType;
use SailCMS\Models\User;
use SailCMS\Sail;
use SailCMS\Text;
use SailCMS\Types\EntryStatus;
use SailCMS\Types\LocaleField;
use SailCMS\Types\Username;

beforeAll(function () {
    $_ENV['SITE_URL'] = 'http://localhost:8888';
    Sail::setAppState(Sail::STATE_CLI);

    $authorModel = new User();
    $username = new Username('Test', 'Entry', 'Test Entry');
    $userId = $authorModel->create($username, 'testentry@leeroy.ca', 'Hell0W0rld!', Collection::init());
    User::$currentUser = $authorModel->getById($userId);
});

afterAll(function () {
    $authorModel = new User();
    $authorModel->removeByEmail('testentry@leeroy.ca');
});

test('Create an entry type', function () {
    $model = new EntryType();

    try {
        $id = $model->create('test', 'Test', 'test', null, false);
        expect($id)->not->toBe('');
    } catch (Exception $exception) {
        expect(true)->toBe(false);
    }
});

test('Create an entry layout', function () {
    $model = new EntryLayout();

    $labels = new LocaleField([
        'fr' => 'Titre',
        'en' => 'Title'
    ]);
    $textField = new TextField($labels, [
        ['required' => true,],
    ]);

    $schema = EntryLayout::generateLayoutSchema(new Collection([
        $textField
    ]));

    try {
        $titles = new LocaleField([
            'fr' => 'Test de disposition',
            'en' => 'Layout Test'
        ]);
        $entryLayout = $model->create($titles, $schema);
        expect($entryLayout->_id)->not->toBe('');
    } catch (Exception $exception) {
//        print_r($exception->getTraceAsString());
        expect(true)->toBe(false);
    }
});

test('Update an entry layout', function () {
    $model = new EntryLayout();
    $entryLayout = $model->one([
        'titles.fr' => 'Test de disposition'
    ]);

    // key the first key
    $fieldKey = $entryLayout->schema->keys()->unwrap()[0];

    $entryLayout->updateSchemaConfig($fieldKey, [
        'max_length' => 255,
        'min_length' => 10
    ]);

    try {
        $result = $model->updateById($entryLayout->_id, null, $entryLayout->schema);
        $updatedEntryLayout = $model->one([
            '_id' => $entryLayout->_id
        ]);
        expect($result)->toBe(true);
        expect($updatedEntryLayout->schema->get($fieldKey . ".configs.0.max_length"))->toBe(255);
        expect($updatedEntryLayout->schema->get($fieldKey . ".configs.0.min_length"))->toBe(10);
    } catch (Exception $exception) {
//        print_r($exception->getMessage());
//        print_r($exception->getTraceAsString());
        expect(true)->toBe(false);
    }
});

test('Failed to create an entry type because the handle is already in use', function () {
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

test('Create an entry with the default type', function () {
    $model = new Entry();

    try {
        $entry = $model->create(true, 'fr', EntryStatus::LIVE, 'Home', null, []);
        expect($entry->title)->toBe('Home');
        expect($entry->status)->toBe(EntryStatus::LIVE->value);
        expect($entry->locale)->toBe('fr');
        expect($entry->slug)->toBe(Text::slugify($entry->title, "fr"));
    } catch (Exception $exception) {
//        print_r($exception->getMessage());
//        print_r($exception->getTrace());
        expect(true)->toBe(false);
    }
});

test('Create an entry with an entry type', function () {
    $entryModel = EntryType::getEntryModelByHandle('test');

    try {
        $entry = $entryModel->create(true, 'fr', EntryStatus::LIVE, 'Test', 'test', []);
        expect($entry->title)->toBe('Test');
        expect($entry->status)->toBe(EntryStatus::LIVE->value);
        expect($entry->locale)->toBe('fr');
        expect($entry->url)->toBe('test/test');
    } catch (Exception $exception) {
        expect(true)->toBe(false);
    }
});

test('Get homepage entry', function () {
    $entry = Entry::getHomepage(Sail::siteId(), true);

    expect($entry->title)->toBe('Test');
});

test('Update an entry type', function () {
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

test('Update an entry with an entry type', function () {
    $entryModel = EntryType::getEntryModelByHandle('test');
    $entry = $entryModel->one([
        'title' => 'Test'
    ]);
    $before = $entry->dates->updated;

    try {
        $result = $entryModel->updateById($entry, [
            'slug' => 'test-de-test',
        ]);
        $entry = $entryModel->one([
            'title' => 'Test'
        ]);
        expect($result)->toBe(true);
        expect($entry)->not->toBe(null);
        expect($entry->dates->updated)->toBeGreaterThan($before);
    } catch (Exception $exception) {
        expect(true)->toBe(false);
    }
});

test('Create an entry with an entry type with an existing url', function () {
    $entryModel = EntryType::getEntryModelByHandle('test');

    try {
        $entry = $entryModel->create(false, 'fr', EntryStatus::INACTIVE, 'Test 2', 'test-de-test', []);
        expect($entry->title)->toBe('Test 2');
        expect($entry->status)->toBe(EntryStatus::INACTIVE->value);
        expect($entry->locale)->toBe('fr');
        expect($entry->url)->toBe('test-pages/test-de-test-2');
    } catch (Exception $exception) {
//        print_r($exception->getMessage());
        expect(true)->toBe(false);
    }
});

test('Fail to create an entry that is trashed', function () {
    $entryModel = EntryType::getEntryModelByHandle('test');

    try {
        $entry = $entryModel->create(false, 'fr', EntryStatus::TRASH, 'Test 2', 'test-de-test', []);
        expect(true)->toBe(false);
    } catch (Exception $exception) {
        expect(true)->toBe(true);
        expect($exception->getMessage())->toBe(Entry::STATUS_CANNOT_BE_TRASH);
    }
});

test('Get a validated slug for an existing slug', function () {
    $newSlug = Entry::getValidatedSlug('test-pages', 'test-de-test', Sail::siteId(), 'fr');

    expect($newSlug)->toBe('test-de-test-3');
});

test('Update an entry with the default type', function () {
    $model = new Entry();
    $entry = $model->one([
        'title' => 'Home'
    ]);
    $before = $entry->dates->updated;

    try {
        $result = $entry->updateById($entry, [
            'title' => 'Home page',
        ]);
        $entry = $model->one([
            'title' => 'Home page'
        ]);
        expect($result)->toBe(true);
        expect($entry)->not->toBe(null);
        expect($entry->dates->updated)->toBeGreaterThan($before);
    } catch (Exception $exception) {
        expect(true)->toBe(false);
    }
});

test('Fail to update an entry to trash', function () {
    $model = new Entry();
    $entry = $model->one([
        'title' => 'Home page'
    ]);

    try {
        $result = $entry->updateById($entry, [
            'status' => EntryStatus::TRASH,
        ]);
        expect(true)->toBe(false);
    } catch (Exception $exception) {
        expect(true)->toBe(true);
        expect($exception->getMessage())->toBe(Entry::STATUS_CANNOT_BE_TRASH);
    }
});

test('Find the entry by url', function () {
    $entry = Entry::findByURL('test-pages/test-de-test', false);

    expect($entry->title)->toBe('Test');
});

test('Failed to find the entry by url', function () {
    $entry = Entry::findByURL('test-pages/test-de-test-2', false);

    expect($entry)->toBe(null);
});

test('Failed to delete an entry type with related entries in it', function () {
    $model = new EntryType();
    $entryType = $model->getByHandle('test');

    try {
        $result = $model->hardDelete($entryType->_id);
        expect($result)->toBe(false);
    } catch (EntryException $exception) {
        expect($exception->getMessage())->toBe(EntryType::CANNOT_DELETE);
        expect(true)->toBe(true);
    }
});

test('Hard delete an entry with an entry type', function () {
    $entryModel = EntryType::getEntryModelByHandle('test');
    $entry = $entryModel->one([
        'title' => 'Test'
    ]);

    try {
        $result = $entryModel->delete($entry->_id, Sail::siteId(), false);
        expect($result)->toBe(true);
    } catch (EntryException $exception) {
        // print_r($exception->getMessage());
        expect(true)->toBe(false);
    }
});

test('Hard delete an entry with an entry type 2', function () {
    $entryModel = EntryType::getEntryModelByHandle('test');
    $entry = $entryModel->one([
        'title' => 'Test 2'
    ]);

    try {
        $result = $entryModel->delete($entry->_id, Sail::siteId(), false);
        expect($result)->toBe(true);
    } catch (EntryException $exception) {
//        print_r($exception->getMessage());
        expect(true)->toBe(false);
    }
});

test('Soft Delete an entry with the default type', function () {
    $model = new Entry();
    $entry = $model->one([
        'title' => 'Home page'
    ]);

    try {
        $result = $entry->delete($entry->_id);
        $entry = $model->one([
            'title' => 'Home page'
        ]);
        expect($result)->toBe(true);
        expect($entry->status)->toBe(EntryStatus::TRASH->value);
    } catch (EntryException $exception) {
        expect(true)->toBe(false);
    }
});

test('Hard Delete an entry with the default type', function () {
    $model = new Entry();
    $entry = $model->one([
        'title' => 'Home page'
    ]);

    try {
        $result = $entry->delete($entry->_id, Sail::siteId(), false);
        expect($result)->toBe(true);
    } catch (EntryException $exception) {
        expect(true)->toBe(false);
    }
});

test('Delete an entry type', function () {
    $model = new EntryType();
    $entryType = $model->getByHandle('test');

    try {
        $result = $model->hardDelete($entryType->_id);
        expect($result)->toBe(true);
    } catch (EntryException $exception) {
        expect(true)->toBe(false);
    }
    $entryType = $model->getByHandle('test');
    expect($entryType)->toBe(null);
});

test('Hard delete an entry layout', function () {
    $model = new EntryLayout();
    $entryLayout = $model->one([
        'titles.fr' => 'Test de disposition'
    ]);

    try {
        $result = $model->delete($entryLayout->_id, false);
        expect($result)->toBe(true);
    } catch (Exception $exception) {
        expect(true)->toBe(false);
    }
});

test('Fail to get homepage entry', function () {
    $entry = Entry::getHomepage(Sail::siteId(), true);

    expect($entry)->toBe(null);
});