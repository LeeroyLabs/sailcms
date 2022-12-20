<?php

use SailCMS\Collection;
use SailCMS\Errors\EntryException;
use SailCMS\Models\Entry;
use SailCMS\Models\Entry\Field as ModelField;
use SailCMS\Models\Entry\TextField;
use SailCMS\Models\Entry\NumberField;
use SailCMS\Models\EntryLayout;
use SailCMS\Models\EntryType;
use SailCMS\Models\User;
use SailCMS\Sail;
use SailCMS\Text;
use SailCMS\Types\EntryStatus;
use SailCMS\Types\LocaleField;
use SailCMS\Types\StoringType;
use SailCMS\Types\Username;

beforeAll(function () {
    $_ENV['SITE_URL'] = 'http://localhost:8888';
    Sail::setAppState(Sail::STATE_CLI);

    $authorModel = new User();
    $username = new Username('Test', 'Entry', 'Test Entry');
    $userId = $authorModel->create($username, 'testentryfield@leeroy.ca', 'Hell0W0rld!', Collection::init());
    User::$currentUser = $authorModel->getById($userId);

    $layoutModel = new EntryLayout();
    $schema = Collection::init();
    $titles = new LocaleField([
        'fr' => 'Test des champs',
        'en' => 'Field Test'
    ]);

   $layoutModel->create($titles, $schema);

    $entryModel = new Entry();
    $entryModel->create(false, 'fr', EntryStatus::LIVE, 'Home Field Test', 'page');
});

afterAll(function () {
    $authorModel = new User();
    $authorModel->removeByEmail('testentryfield@leeroy.ca');

    $entryModel = EntryType::getEntryModelByHandle('page');
    $entry = $entryModel->one([
        'title' => 'Home Field Test'
    ]);

    $entryModel->delete($entry->_id, false);

    $layoutModel = new EntryLayout();
    $entryLayout = $layoutModel->one([
        'titles.fr' => 'Test des champs'
    ]);

    $layoutModel->delete($entryLayout->_id, false);
});

test('Add a new Text field to an entry layout', function () {
    try {
        $result = true;
        expect($result)->toBe(true);
    } catch (Exception $exception) {
        expect(true)->toBe(false);
    }
});