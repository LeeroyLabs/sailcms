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

/** TODO: Create entry layout + entry */

beforeAll(function () {
    $_ENV['SITE_URL'] = 'http://localhost:8888';
    Sail::setAppState(Sail::STATE_CLI);

    $authorModel = new User();
    $username = new Username('Test', 'Entry', 'Test Entry');
    $userId = $authorModel->create($username, 'testentry@leeroy.ca', 'Hell0W0rld!', Collection::init());
    User::$currentUser = $authorModel->getById($userId);
});

/** TODO: Delete the entry layout + entry */

afterAll(function () {
    $authorModel = new User();
    $authorModel->removeByEmail('testentry@leeroy.ca');
});