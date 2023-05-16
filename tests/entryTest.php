<?php

use SailCMS\Collection;
use SailCMS\Errors\EntryException;
use SailCMS\Models\Entry;
use SailCMS\Models\Entry\TextField;
use SailCMS\Models\EntryLayout;
use SailCMS\Models\EntryPublication;
use SailCMS\Models\EntrySeo;
use SailCMS\Models\EntryType;
use SailCMS\Models\EntryVersion;
use SailCMS\Sail;
use SailCMS\Text;
use SailCMS\Types\LocaleField;
use SailCMS\Types\SocialMeta;

beforeAll(function () {
    Sail::setupForTests(__DIR__);

    // Ensure default type exists
    EntryType::getDefaultType();
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
        'title' => $textField
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
})->group('entry-layout');

test('Update the config of an entry layout', function () {
    $model = new EntryLayout();
    $entryLayout = $model->bySlug('layout-test');

    $entryLayout->updateSchemaConfig('title', [
        'maxLength' => 255,
        'minLength' => 10
    ], 0, new LocaleField([
        'fr' => 'Titre de section',
        'en' => 'Section title'
    ]));

    try {
        $result = $model->updateById($entryLayout->_id, null, $entryLayout->schema);
        $updatedEntryLayout = $model->one([
            '_id' => $entryLayout->_id
        ]);

        expect($result)->toBe(true)
            ->and($updatedEntryLayout->schema->get("title.configs.0.maxLength"))->toBe(255)
            ->and($updatedEntryLayout->schema->get("title.configs.0.minLength"))->toBe(10)
            ->and($updatedEntryLayout->schema->get("title.configs.0.labels")->fr)->toBe('Titre de section');
    } catch (Exception $exception) {
//        print_r($exception->getMessage());
//        print_r($exception->getTraceAsString());
        expect(true)->toBe(false);
    }
})->group('entry-layout');

test('Update a key of a entry layout schema', function () {
    $model = new EntryLayout();
    $entryLayout = $model->bySlug('layout-test');

    try {
        $entryLayout->updateSchemaKey('title', 'sub title');
        $entryLayout = $model->bySlug('layout-test');
        expect($entryLayout->schema->get('title'))->toBe(null)
            ->and($entryLayout->schema->get('sub title.handle'))->toBe('SailCMS-Models-Entry-TextField');
    } catch (Exception $exception) {
//        print_r($exception->getMessage());
//        print_r($exception->getTraceAsString());
        expect(true)->toBe(false);
    }
})->group('entry-layout');

test('Failed to update a key of a entry layout schema', function () {
    $model = new EntryLayout();
    $entryLayout = $model->bySlug('layout-test');

    try {
        $entryLayout->updateSchemaKey('title', 'sub title');
        expect(true)->toBe(false);
    } catch (Exception $exception) {
//        print_r($exception->getMessage());
//        print_r($exception->getTraceAsString());
        expect($exception->getMessage())->toBe('6005: The given key "title" does not exists in the schema.');
    }
})->group('entry-layout');

test('Create an entry type', function () {
    $model = new EntryType();

    $url_prefix = new LocaleField(['fr' => 'test', 'en' => 'test']);

    try {
        $id = $model->create('test', 'Test', $url_prefix, null, false);
        expect($id)->not->toBe('');
    } catch (Exception $exception) {
        expect(true)->toBe(false);
    }
})->group('entry-type');

test('Fail to create an entry type because the handle use a reserved word', function () {
    $model = new EntryType();

    $url_prefix = new LocaleField(['fr' => 'entrée', 'en' => 'entry']);

    try {
        $id = $model->create('entry', 'Entry', $url_prefix, null, false);
        expect(true)->toBe(false);
    } catch (Exception $exception) {
        expect($exception->getMessage())->toBe(sprintf(EntryType::HANDLE_USE_RESERVED_WORD, 'entry'));
    }
})->group('entry-type');

test('Failed to create an entry type because the handle is already in use', function () {
    $model = new EntryType();
    $url_prefix = new LocaleField(['fr' => 'test', 'en' => 'test']);

    try {
        $model->create('test', 'Test', $url_prefix, null, false);
        expect(true)->toBe(false);
    } catch (EntryException $exception) {
        expect($exception->getMessage())->toBe(EntryType::HANDLE_ALREADY_EXISTS);
    } catch (Exception $otherException) {
        expect(true)->toBe(false);
    }
})->group('entry-type');

test('Create an entry with the default type', function () {
    $model = new Entry();

    try {
        $entry = $model->create(true, 'fr', 'Home', 'home');

        expect($entry->title)->toBe('Home')
            ->and($entry->locale)->toBe('fr')
            ->and($entry->slug)->toBe(Text::from($entry->title)->slug('fr')->value());
    } catch (Exception $exception) {
//        print_r($exception->getMessage());
        expect(true)->toBe(false);
    }
})->group('entry');

test('Access to default SEO data', function () {
    $entry = (new Entry())->one(['title' => 'Home']);

    $seo = $entry->getSEO();

    expect($seo->title)->toBe($entry->title)
        ->and($seo->_id)->not->toBeNull();
});

test('Update seo data for an entry', function () {
    $entry = (new Entry())->one(['title' => 'Home']);

    $entrySeoModel = new EntrySeo();

    $socialMetas = Collection::init();
    $socialMetas->push(new SocialMeta('facebook', (object)[
        'type' => 'article',
        'app_id' => 'fake-id-0123456'
    ]));
    $socialMetas->push(new SocialMeta('twitter', (object)[
        'card' => 'summary',
        'image' => 'fake/path',
        'image:alt' => 'Fake image',
    ]));

    try {
        $entrySeoModel->createOrUpdate($entry->_id, "New Title", new Collection([
            'description' => "This is a really good description for a page",
            'keywords' => "Good, CMS",
            'social_metas' => $socialMetas
        ]));
        $entrySeo = $entry->getSEO(true);

        expect($entrySeo->get('title'))->toBe("New Title")
            ->and($entrySeo->get('description'))->toBe("This is a really good description for a page")
            ->and($entrySeo->get('keywords'))->toBe("Good, CMS")
            ->and($entrySeo->get('social_metas.0.handle'))->toBe('facebook')
            ->and($entrySeo->get('social_metas.0.content.app_id'))->toBe('fake-id-0123456')
            ->and($entrySeo->get('social_metas.1.handle'))->toBe('twitter')
            ->and($entrySeo->get('social_metas.1.content.image:alt'))->toBe('Fake image');
    } catch (Exception $exception) {
//        print_r($exception->getTraceAsString());
        expect(true)->toBe(false);
    }
})->group('entry-seo');

test('Create an entry with an entry type', function () {
    $entryModel = EntryType::getEntryModelByHandle('test');

    try {
        $entry = $entryModel->create(true, 'fr', 'Test', 'test', 'test');
        expect($entry->title)->toBe('Test');
        expect($entry->locale)->toBe('fr');
        expect($entry->url)->toBe('test/test');
    } catch (Exception $exception) {
        //print_r($exception);
        expect(true)->toBe(false);
    }
})->group('entry');

test('Update an entry type', function () {
    $model = new EntryType();
    $entryLayout = (new EntryLayout())->bySlug('layout-test');

    try {
        $result = $model->updateByHandle('test', new Collection([
            'title' => 'Test Pages',
            'url_prefix' => new LocaleField([
                'en' => 'test-pages',
                'fr' => 'pages-de-test'
            ]),
            'entry_layout_id' => $entryLayout->_id
        ]));
        expect($result)->toBe(true);
        $entryType = $model->getByHandle('test');
        expect($entryType->title)->toBe('Test Pages')
            ->and($entryType->url_prefix->en)->toBe('test-pages');
    } catch (Exception $exception) {
        expect(true)->toBe(false);
    }
})->group('entry-layout');

test('Get homepage entry', function () {
    $entry = Entry::getHomepageEntry(Sail::siteId(), 'fr');

    expect($entry->title)->toBe('Test');
})->group('entry');

test('Publish an entry', function () {
    $entryModel = EntryType::getEntryModelByHandle('test');
    $entry = $entryModel->one([
        'title' => 'Test'
    ]);

    try {
        $now = time();
        $expiration = $now + 3600; // + 1 hour
        $entryPublicationId = $entryModel->publish($entry->_id, $now, $expiration);
        expect($entryPublicationId)->not()->toBeNull();
    } catch (Exception $exception) {
//        print_r($exception);
        expect(false)->toBeTrue();
    }
});

test('Update an entry with an entry type', function () {
    $entryModel = EntryType::getEntryModelByHandle('test');
    $entry = $entryModel->one([
        'title' => 'Test'
    ]);

    try {
        $result = $entryModel->updateById($entry, [
            'slug' => 'test-de-test',
            'is_homepage' => false,
            'content' => [
                'sub title' => "had content!!!"
            ],
        ]);
        $entry = $entryModel->one([
            'title' => 'Test'
        ]);
        expect($result->length)->toBe(0)
            ->and($entry)->not->toBe(null)
            ->and($entry->slug)->toBe('test-de-test');
    } catch (Exception $exception) {
//        print_r($exception->getMessage());
        expect(true)->toBe(false);
    }
})->group('entry');

test('Fail to get homepage entry', function () {
    $entry = Entry::getHomepageEntry(Sail::siteId(), 'fr');

    expect($entry)->toBe(null);
})->group('entry');

test('Create an entry with an entry type with an existing url', function () {
    $entryModel = EntryType::getEntryModelByHandle('test');

    try {
        $entry = $entryModel->create(false, 'fr', 'Test 2', 'test', 'test-de-test', [
            'content' => [
                'sub title' => "Textfield content"
            ]
        ]);
        expect($entry->title)->toBe('Test 2')
            ->and($entry->locale)->toBe('fr')
            ->and($entry->url)->toBe('pages-de-test/test-de-test-2');
    } catch (Exception $exception) {
        // print_r($exception->getMessage());
        expect(true)->toBe(false);
    }
})->group('entry');

test('Get a validated slug for an existing slug', function () {
    $newSlug = Entry::getValidatedSlug(new LocaleField([
        'en' => 'test-pages',
        'fr' => 'pages-de-test'
    ]), 'test-de-test', Sail::siteId(), 'fr');

    expect($newSlug)->toBe('test-de-test-3');
})->group('entry');

test('Failed to update content because a field does not validated', function () {
    $entryModel = EntryType::getEntryModelByHandle('test');
    $entry = $entryModel->one([
        'title' => 'Test 2'
    ]);
    $entry->content->{"sub title"} = "TooShort";

    try {
        $entryModel->updateById($entry->_id, [
            'content' => $entry->content
        ]);
        expect(true)->toBe(false);
    } catch (EntryException $exception) {
//        print_r($exception->getMessage());
        expect($exception->getMessage())->toBe("5005: The content has theses errors :" . PHP_EOL . "6121: The content is too short (10) (sub title)");
    }
})->group('entry');

test('Update an entry with the default type', function () {
    $model = new Entry();
    $entry = $model->one([
        'title' => 'Home'
    ]);
    $before = $entry->dates->updated;

    try {
        $result = $entry->updateById($entry, [
            'title' => 'Home page',
            'is_homepage' => true
        ]);
        $entry = $model->one([
            'title' => 'Home page'
        ]);
        expect($result->length)->toBe(0)
            ->and($entry)->not->toBe(null);
    } catch (Exception $exception) {
//        print_r($exception->getMessage());
        //print_r($exception->getTraceAsString());
        expect(true)->toBe(false);
    }
})->group('entry');

test('Get homepage entry after update', function () {
    $entry = Entry::getHomepageEntry(Sail::siteId(), 'fr');
    expect($entry->title)->toBe('Home page');
})->group('entry');

test('Find the entry by url', function () {
    $entry = Entry::findByURL('fr/', false);

    expect($entry->title)->toBe('Test')
        ->and($entry->slug)->toBe('test')
        ->and($entry->content->length)->toBe(0); // We have the entry published before the content has been added
})->group('entry');

test('Unpublish an entry', function () {
    $entryModel = EntryType::getEntryModelByHandle('test');
    $entry = $entryModel->one([
        'title' => 'Test'
    ]);

    try {
        $result = $entryModel->unpublish($entry->_id);
        $publicationEmpty = (new EntryPublication())->getPublicationByEntryId($entry->_id);
        expect($result)->toBeTrue()
            ->and($publicationEmpty)->toBeNull();
    } catch (Exception $exception) {
//        print_r($exception);
        expect(false)->toBeTrue();
    }
});

test('Failed to find the entry by url', function () {
    // Failed to find because it does not exist
    $entry = Entry::findByURL('pages-de-test/test-de-test-2', false);
    expect($entry)->toBe(null);

    // Failed to find because it's not published yet
    $entry = Entry::findByURL('pages-de-test/test-de-test', false);
    expect($entry)->toBe(null);
})->group('entry');

test('Get entry versions, failed to apply the last version and apply one', function () {
    $entryModel = EntryType::getEntryModelByHandle('test');
    $entry = $entryModel->one([
        'title' => 'Test'
    ]);

    $entryVersionModel = new EntryVersion();
    $entryVersions = $entryVersionModel->getVersionsByEntryId($entry->_id);

    expect(count($entryVersions))->toBe(2);

    $versionToApply = $entryVersions[0];
    $lastVersion = end($entryVersions);

    try {
        $entryVersionModel->applyVersion($lastVersion->_id);
        expect(false)->toBe(true);
    } catch (EntryException $e) {
        expect($e->getMessage())->toBe(EntryVersion::CANNOT_APPLY_LAST_VERSION);
    }

    try {
        $result = $entryVersionModel->applyVersion($versionToApply->_id);
        expect($result)->toBe(true);

        $entry = $entryModel->one([
            'title' => 'Test'
        ]);
        // The first versions has empty content
        expect($entry->content->length)->toBe(0)
            ->and($entry->slug)->toBe('test');
    } catch (EntryException $e) {
//        SailCMS\Debug::ray($e);
        expect(true)->toBe(false);
    }
})->group('entry-version');

test('Failed to delete an entry type with related entries in it', function () {
    $model = new EntryType();
    $entryType = $model->getByHandle('test');

    try {
        $result = $model->hardDelete($entryType->_id);
        expect($result)->toBe(false);
    } catch (EntryException $exception) {
        expect($exception->getMessage())->toBe(EntryType::CANNOT_DELETE);
    }
})->group('entry-type');

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
        expect($result)->toBe(true)
            ->and($entry->trashed)->toBeTrue();
    } catch (EntryException $exception) {
        expect(true)->toBe(false);
    }
})->group('entry-type');

test('Get all entries with ignoring trash and not', function () {
    $model = new Entry();

    try {
        $ignoredTrash = $model->all();
        $notIgnoreTrash = $model->all(false);

        expect($ignoredTrash->length)->toBe(1)
            ->and($notIgnoreTrash->length)->toBe(0);
    } catch (Exception $exception) {
//        print_r($exception->getMessage());
        expect(true)->toBe(false);
    }
})->group('entry');

test('Hard delete an entry with an entry type', function () {
    $entryModel = EntryType::getEntryModelByHandle('test');
    $entry = $entryModel->one([
        'title' => 'Test'
    ]);

    try {
        $result = $entryModel->delete($entry->_id, false);
        expect($result)->toBe(true);
    } catch (EntryException $exception) {
        // print_r($exception->getMessage());
        expect(true)->toBe(false);
    }
})->group('entry');

test('Hard delete an entry with an entry type 2', function () {
    $entryModel = EntryType::getEntryModelByHandle('test');
    $entry = $entryModel->one([
        'title' => 'Test 2'
    ]);

    try {
        $result = $entryModel->delete($entry->_id, false);
        expect($result)->toBe(true);
    } catch (EntryException $exception) {
//        print_r($exception->getMessage());
        expect(true)->toBe(false);
    }
})->group('entry');

test('Hard Delete an entry with the default type', function () {
    $model = new Entry();
    $entry = $model->one([
        'title' => 'Home page'
    ]);

    try {
        $result = $entry->delete($entry->_id, false);
        expect($result)->toBe(true);
    } catch (EntryException $exception) {
        expect(true)->toBe(false);
    }

    // Check if seo has been deleted
    $entrySeo = (new EntrySeo())->getByEntryId($entry->_id, false);
    expect($entrySeo)->toBeNull();
})->group('entry');

test('Fail to delete an entry layout because it is used', function () {
    $model = new EntryLayout();
    $entryLayout = $model->bySlug('layout-test');

    try {
        $result = $model->delete($entryLayout->_id);
        expect($result)->toBe(false);
    } catch (Exception $exception) {
        expect($exception->getMessage())->toBe(EntryLayout::SCHEMA_IS_USED);
    }
})->group('entry-layout');

test('Delete an entry type', function () {
    $model = new EntryType();
    $entryType = $model->getByHandle('test');

    try {
        $result = $model->hardDelete($entryType->_id);
        expect($result)->toBe(true);
    } catch (EntryException $exception) {
//        print_r($exception->getMessage());
        expect(true)->toBe(false);
    }
    $entryType = $model->getByHandle('test');
    expect($entryType)->toBe(null);
})->group('entry-type');

test('Soft delete an entry layout', function () {
    $model = new EntryLayout();
    $entryLayout = $model->bySlug('layout-test');

    try {
        $result = $model->delete($entryLayout->_id);
        $deletedEntryLayout = $model->bySlug('layout-test');
        expect($result)->toBe(true)
            ->and($deletedEntryLayout->is_trashed)->toBe(true);
    } catch (Exception $exception) {
        expect(true)->toBe(false);
    }
})->group('entry-layout');

test('Hard delete an entry layout', function () {
    $model = new EntryLayout();
    $entryLayout = $model->bySlug('layout-test');

    try {
        $result = $model->delete($entryLayout->_id, false);
        expect($result)->toBe(true);
    } catch (Exception $exception) {
        expect(true)->toBe(false);
    }
})->group('entry-layout');

test('Fail to get homepage entry after deletion', function () {
    $entry = Entry::getHomepageEntry(Sail::siteId(), 'fr');

    expect($entry)->toBe(null);
})->group('entry');
