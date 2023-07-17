<?php

use SailCMS\Collection;
use SailCMS\Errors\EntryException;
use SailCMS\Models\Entry;
use SailCMS\Models\EntryField;
use SailCMS\Models\EntryLayout;
use SailCMS\Models\EntryPublication;
use SailCMS\Models\EntrySeo;
use SailCMS\Models\EntryType;
use SailCMS\Models\EntryVersion;
use SailCMS\Sail;
use SailCMS\Text;
use SailCMS\Types\EntryLayoutTab;
use SailCMS\Types\LocaleField;
use SailCMS\Types\SocialMeta;

beforeAll(function () {
    Sail::setupForTests(__DIR__);

    // Ensure default type exists
    EntryType::getDefaultType();
});

test('Create an entry field', function () {
    $model = new EntryField();

    $entryField = $model->create(new Collection([
        'key' => 'test',
        'name' => 'Test',
        'label' => new LocaleField(['en' => 'Test', 'fr' => 'Test']),
        'type' => 'text',
        'required' => true,
        'searchable' => true
    ]));
    $model->create(new Collection([
        'key' => 'test_2',
        'name' => 'Email',
        'label' => new LocaleField(['en' => 'Test', 'fr' => 'Test']),
        'type' => 'text',
        'validation' => 'email',
        'required' => true
    ]));

    expect($entryField->key)->toBe('test')
        ->and($entryField->_id)->not->toBeNull()
        ->and($entryField->searchable)->toBeTrue();
});

test('Fail to create an entry field', function () {
    $model = new EntryField();

    try {
        $model->create(new Collection([
            'key' => 'test',
            'name' => 'Test',
            'label' => new LocaleField(['en' => 'Test', 'fr' => 'Test']),
            'type' => 'text',
            'required' => true
        ]));
        expect(false)->toBeTrue();
    } catch (EntryException $exception) {
        expect($exception->getMessage())->toBe(sprintf(EntryField::KEY_ERROR, 'test'));
    }
});

test('Create an entry layout', function () {
    $model = new EntryLayout();
    $testField = EntryField::getByKey('test');

    $schema = new Collection();
    $schema->push(new EntryLayoutTab('Test', [(string)$testField->_id]));
    $schema->push(new EntryLayoutTab('Test 2', [(string)$testField->_id, (string)EntryField::getByKey('test_2')->_id]));

    try {
        $entryLayout = $model->create('Layout Test', $schema);
        expect($entryLayout->_id)->not->toBe('');
    } catch (Exception $exception) {
        expect(true)->toBe(false);
    }
})->group('entry-layout');

test('Fail to create an entry layout', function () {
    $model = new EntryLayout();
    $schema = new Collection();
    $schema->push(new EntryLayoutTab('Test', ['bugs!']));

    try {
        $model->create('Error', $schema);
        expect(false)->toBeTrue();
    } catch (Exception $exception) {
        expect($exception->getMessage())->toBe(sprintf(EntryLayout::SCHEMA_INVALID_TAB_FIELD_ID, 1));
    }

    $schema = new Collection();
    $schema->push(['label' => 'No good', 'fields' => []]);

    try {
        $model->create('Error', $schema);
        expect(false)->toBeTrue();
    } catch (Exception $exception) {
        expect($exception->getMessage())->toBe(sprintf(EntryLayout::SCHEMA_INVALID_TAB_VALUE, 1));
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
            'entry_layout_id' => $entryLayout->_id,
            'use_categories' => true
        ]));
        expect($result)->toBe(true);
        $entryType = $model->getByHandle('test');
        expect($entryType->title)->toBe('Test Pages')
            ->and($entryType->url_prefix->en)->toBe('test-pages')
            ->and($entryType->use_categories)->toBeTrue();
    } catch (Exception $exception) {
        expect(true)->toBe(false);
    }
})->group('entry-type');

test('Create an entry with an entry type', function () {
    $entryModel = EntryType::getEntryModelByHandle('test');

    try {
        $entry = $entryModel->create(true, 'fr', 'Test', 'test', 'test', new Collection([
            'content' => [
                'test' => 'Test',
                'test_2' => 'philippe@leeroy.ca'
            ]
        ]));

        expect($entry->title)->toBe('Test');
        expect($entry->locale)->toBe('fr');
        expect($entry->url)->toBe('pages-de-test/test');
    } catch (Exception $exception) {
//        \SailCMS\Debug::ray($exception);
        expect(true)->toBe(false);
    }
})->group('entry');

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
            'content' => null,
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
    $entry = Entry::findByURL('fr/', Sail::siteId(), false);

    expect($entry->title)->toBe('Test')
        ->and($entry->slug)->toBe('test')
        ->and($entry->content->length)->toBe(2); // We have the entry published before the content has been added
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
        expect($entry->content->length)->toBe(2)
            ->and($entry->slug)->toBe('test');
    } catch (EntryException $e) {
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

test('Update an entry layout', function () {
    $model = new EntryLayout();
    $entryLayout = $model->bySlug('layout-test');

    $schema = Collection::init();
    $schema->push(new EntryLayoutTab('Test', [(string)EntryField::getByKey('test')->_id]));
    $schema->push(new EntryLayoutTab('Test 2', [(string)EntryField::getByKey('test_2')->_id]));

    try {
        $entryLayout->updateById($entryLayout->_id, 'Layout to delete', $schema, null);
        $entryLayout = $model->bySlug('layout-test');

        expect($entryLayout->title)->toBe('Layout to delete')
            ->and($entryLayout->schema[0]->label)->toBe($schema[0]->label)
            ->and(count($entryLayout->schema[1]->fields))->toBe(1);
    } catch (Exception $exception) {
        expect(true)->not->toBeTrue();
    }
})->group('entry-layout');

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

test('Delete an entry field', function () {
    $model = new EntryField();

    $result = $model->deleteByIdOrKey(null, 'test');
    $model->deleteByIdOrKey(null, 'test_2');

    expect($result)->toBeTrue();
});
