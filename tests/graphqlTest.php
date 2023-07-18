<?php

use SailCMS\Sail;
use SailCMS\Test\GraphQLClient;

beforeEach(function () {
    Sail::setupForTests(__DIR__);

    $graphqlUrl = env('TEST_GRAPHQL_URL');
    $userEmail = env('TEST_USER_EMAIL');
    $userPWD = env('TEST_USER_PWD');

    if ($graphqlUrl) {
        $this->client = new GraphQLClient($graphqlUrl);

        $authTmpResponse = $this->client->run('
            query ($email: String!, $password: String!){
                authenticate(email: $email, password: $password) {
                    message
                }
            }
        ', ['email' => $userEmail, 'password' => $userPWD]);

        $tmpToken = $authTmpResponse->data->authenticate->message;

        $authResponse = $this->client->run('
            query ($token: String!) {
                verifyAuthenticationToken(token: $token) {
                    auth_token
                    _id
                }
            }
        ', ['token' => $tmpToken]);

        $_ENV['test-token'] = $authResponse->data->verifyAuthenticationToken->auth_token;
    }
});

test('Create asset', function () {

    if (isset($_ENV['test-token'])) {
        $data = file_get_contents(__DIR__ . '/mock/asset/test.jpg.txt');
        $newAsset = $this->client->run('
            mutation {
                uploadAsset(
                    src: "' . $data . '"
                    filename: "graphql-test.jpg"
                    site_id: "' . Sail::siteId() . '"
                ) {
                    _id
                }
            }
        ', [], $_ENV['test-token']);
        expect($newAsset->status)->toBe('ok');
    }
});

test('Get a page and modify his SEO', function () {
    if (isset($_ENV['test-token'])) {
        $entryResponse = $this->client->run('
            query {
                entries(entry_type_handle: "page") {
                    list {
                        _id
                    }
                }
            }
        ', [], $_ENV['test-token']);

        $id = $entryResponse->data->entries->list[0]->_id;

        $response = $this->client->run('
            mutation {
                updateEntrySeo(
                    entry_id: "' . $id . '"
                    title: "Another page seo"
                    description: "This is a description"
                    keywords: "Sail, CMS"
                    robots: true
                    sitemap: true
                            social_metas: [
                        {handle: "facebook", content: [
                            {name: "app_id", content: "fake-id-0123456"},
                            {name: "type", content: "article"},
                        ]},
                        {handle: "twitter", content: [
                            {name: "card", content: "summary"},
                            {name: "image", content: "fake-path"},
                            {name: "image:alt", content: "Fake image"},
                        ]}
                    ]
                )
            }
        ', [], $_ENV['test-token']);

        if ($response->status === 'ok' && $response->data->updateEntrySeo) {
            $checkResponse = $this->client->run('
                query {
                    entry(entry_type_handle: "page", id: "' . $id . '") {
                        seo {
                            title
                            description
                            keywords
                            robots
                            sitemap
                            social_metas {
                                handle
                                content {
                                    name
                                    content
                                }
                            }
                        }
                    }
                }
            ', [], $_ENV['test-token']);

            $entrySeo = $checkResponse->data->entry->seo;

            expect($entrySeo->title)->toBe("Another page seo")
                ->and($entrySeo->description)->toBe("This is a description")
                ->and($entrySeo->keywords)->toBe("Sail, CMS")
                ->and($entrySeo->robots)->toBeTrue()
                ->and($entrySeo->sitemap)->toBeTrue()
                ->and($entrySeo->social_metas[0]->handle)->toBe('facebook')
                ->and($entrySeo->social_metas[0]->content[0]->content)->toBe('Another page seo')
                ->and($entrySeo->social_metas[0]->content[3]->name)->toBe('app_id')
                ->and($entrySeo->social_metas[0]->content[4]->content)->toBe('article')
                ->and($entrySeo->social_metas[1]->content[1]->content)->toBe('This is a description')
                ->and($entrySeo->social_metas[1]->handle)->toBe('twitter')
                ->and($entrySeo->social_metas[1]->content[3]->name)->toBe('card')
                ->and($entrySeo->social_metas[1]->content[4]->content)->toBe('Fake image');
        } else {
            expect(true)->toBe(false);
        }
    }
})->group('graphql');

test('Create field and layout', function () {
    if (isset($_ENV['test-token'])) {
        $entryFieldResponse = $this->client->run('
            mutation {
                createEntryField(key: "graphql_test", name: "Text", label: {en: "Text", fr: "Texte"}, type: "text", required: true) {
                    _id
                    key
                }
            }
        ', [], $_ENV['test-token']);

        expect($entryFieldResponse->status)->toBe('ok');

        $entryLayoutResponse = $this->client->run('
            mutation {
                createEntryLayout(title: "GraphQL Test", schema: {label: "First tab", fields: ["' . $entryFieldResponse->data->createEntryField->_id . '"]}, slug: "graphql-test") {
                    _id
                    slug
                }
            }
        ', [], $_ENV['test-token']);

        expect($entryLayoutResponse->status)->toBe('ok')
            ->and($entryLayoutResponse->data->createEntryLayout->slug)->toBe('graphql-test');
    }
});

test('Delete field and layout', function () {
    if (isset($_ENV['test-token'])) {
        $entryLayoutResponse = $this->client->run('
            {
                entryLayout(slug: "graphql-test") {
                    _id
                    slug
                    is_trashed
                    authors {
                        created_by {
                            email
                            name {
                                full
                            }
                        }
                        updated_by {
                            email
                            name {
                                full
                            }
                        }
                        deleted_by {
                            email
                            name {
                                full
                            }
                        }
                    }
                    dates {
                        created
                        updated
                        deleted
                    }
                    schema {
                        label
                        fields {
                            _id
                            key
                            type
                            name
                            label {
                                fr
                                en
                            }
                            placeholder {
                                fr
                                en
                            }
                            explain {
                                fr
                                en
                            }
                            validation
                            repeatable
                            required
                            config
                        }
                    }
                }
            }
        ', [], $_ENV['test-token']);

        $entryLayoutDeleteResponse = $this->client->run('
            mutation {
                deleteEntryLayout(id: "' . $entryLayoutResponse->data->entryLayout->_id . '", soft: false)
            }
        ', [], $_ENV['test-token']);

        expect($entryLayoutDeleteResponse->status)->toBe('ok');

        $entryFieldResponse = $this->client->run('
            mutation {
                deleteEntryField(key: "graphql_test")
            }
        ', [], $_ENV['test-token']);

        expect($entryFieldResponse->status)->toBe('ok');
    }
});

test('Get a entry', function () {
    if (isset($_ENV['test-token'])) {
        $entryResponse = $this->client->run('
        query {
            entries(entry_type_handle: "page", page: 1, limit: 1) {
                list {
                    _id
                    entry_type {
                        _id
                        title
                        handle
                        url_prefix {
                            en
                            fr
                        }
                    }
                    parent {
                        _id
                        title
                        slug
                        url
                        locale
                        site_id
                    }
                    site_id
                    locale
                    alternates {
                        locale
                        url
                        entry_id
                    }
                    is_homepage
                    trashed
                    title
                    template
                    slug
                    url
                    authors {
                        created_by {
                            _id
                            email
                            name {
                                full
                            }
                        }
                        updated_by {
                            _id
                            email
                            name {
                                full
                            }
                        }
                        deleted_by {
                            _id
                            email
                            name {
                                full
                            }
                        }
                    }
                    dates {
                        created
                        updated
                        deleted
                    }
                    categories {
                        _id
                        name {
                            en
                            fr
                        }
                        slug
                    }
                    content {
                        key
                        content
                    }
                    seo {
                        _id
                        title
                        alternates {
                            locale
                            url
                            entry_id
                        }
                        url
                        locale
                        description
                        keywords
                        robots
                        sitemap
                        default_image
                        social_metas {
                            handle
                            content {
                                name
                                content
                            }
                        }
                    }
                }
            }
        }
    ', [], $_ENV['test-token']);
        expect($entryResponse->data->entries->list[0])->not()->toBeNull();
    }
})->group('graphql');

test('Create and soft/hard delete entry layouts', function () {
    if (isset($_ENV['test-token'])) {
        $entryLayoutIds = "";
        $titleAndSlugs = ['to-delete-1', 'to-delete-2'];
        foreach ($titleAndSlugs as $i => $titleAndSlug) {
            $entryLayoutResponse = $this->client->run('
                mutation {
                    createEntryLayout(title: "' . $titleAndSlug . '", schema: [], slug: "' . $titleAndSlug . '") {
                        _id
                        slug
                    }
                }
            ', [], $_ENV['test-token']);
            $entryLayoutIds .= '"' . (string)$entryLayoutResponse->data->createEntryLayout->_id . '"';

            if ($i < count($titleAndSlugs) - 1) {
                $entryLayoutIds .= ', ';
            }
        }

        $entryLayoutSoftDelete = $this->client->run('
            mutation {
                deleteEntryLayouts(ids: [' . $entryLayoutIds . '])
            }
        ', [], $_ENV['test-token']);

        expect($entryLayoutSoftDelete->status)->toBe('ok');

        $entryLayoutHardDelete = $this->client->run('
            mutation {
                deleteEntryLayouts(ids: [' . $entryLayoutIds . '], soft: false)
            }
        ', [], $_ENV['test-token']);

        expect($entryLayoutHardDelete->status)->toBe('ok');
    }
})->group('graphql');

test('Delete test asset', function () {
    if (isset($_ENV['test-token'])) {
        $assets = $this->client->run('
            {
                assets(page: 1, limit: 1, search: "graphql-test-webp", site_id: "' . Sail::siteId() . '") {
                    list {
                        _id
                    }
                }
            }
        ', [], $_ENV['test-token']);

        $assetId = $assets->data->assets->list[0]->_id;
        expect($assetId)->not()->toBeNull();
        $removeAsset = $this->client->run('
            mutation {
                removeAssets(assets: ["' . $assetId . '"])
            }
        ', [], $_ENV['test-token']);

        expect($removeAsset->status)->toBe('ok');
    }
});