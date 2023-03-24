<?php

use SailCMS\Sail;
use SailCMS\Test\GraphQLClient;

beforeEach(function () {
    Sail::setupForTests(__DIR__);

    $graphqlUrl = env('GRAPHQL_URL');
    if ($graphqlUrl) {
        $this->client = new GraphQLClient($graphqlUrl);

        $authTmpResponse = $this->client->run('
            query ($email: String!, $password: String!){
                authenticate(email: $email, password: $password) {
                    message
                }
            }
        ', ['email' => 'philippe@leeroy.ca', 'password' => 'Hell0W0rld!']);

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

// TODO maybe Must create a page instead of getting the first one...

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

test('Create layout, entry type & entry', function () {
    if (isset($_ENV['test-token'])) {
        $newEntryLayout = $this->client->run('
            mutation createLayout {
                createEntryLayout(
                    titles: { fr: "Test des champs graphql", en: "Field tests graphql" }
                    schema: [
                        {
                            labels: { en: "Text", fr: "Texte" }
                            key: "text"
                            handle: "SailCMS-Models-Entry-TextField"
                            inputSettings: [
                                  {
                                        settings: [
                                            { name: "required", value: "1", type: boolean }
                                            { name: "maxLength", value: "10", type: integer }
                                            { name: "minLength", value: "5", type: integer }
                                        ]
                                 }
                            ]
                        }
                        {
                            labels: { en: "Integer", fr: "Entier" }
                            key: "integer"
                            handle: "SailCMS-Models-Entry-NumberField"
                            inputSettings: [
                                {
                                    settings: [
                                        { name: "min", value: "-1", type: integer }
                                        { name: "max", value: "11", type: integer }
                                    ]
                                }
                            ]
                        }
                        {
                            labels: { en: "Float", fr: "Flottant" }
                            key: "float"
                            handle: "SailCMS-Models-Entry-NumberField"
                            inputSettings: [
                                {
                                    settings: [
                                        { name: "required", value: "true", type: boolean }
                                        { name: "min", value: "0.03", type: float }
                                    ]
                                }
                            ]
                        }
                        {
                            labels: { en: "Description", fr: "Description" }
                            key: "desc"
                            handle: "SailCMS-Models-Entry-TextField"
                            inputSettings: []
                        }
                        {
                            labels: { en: "Select", fr: "Selection" }
                            key: "select"
                            handle: "SailCMS-Models-Entry-SelectField"
                            inputSettings: [
                                {
                                    settings: [
                                        { name: "required", value: "false", type: boolean }
                                        {
                                            name: "options"
                                            value: ""
                                            options: [
                                                { label: "Big test", value: "test" }
                                                {
                                                    label: "The real big test"
                                                    value: "test2"
                                                }
                                            ]
                                            type: array
                                        }
                                    ]
                                }
                            ]
                        }
                    ]
                    ) {
                    _id
                }
            }
        ', [], $_ENV['test-token']);

        $newEntryType = $this->client->run('
            mutation {
                createEntryType(
                    handle: "tests-graphql"
                    title: "Tests graphql"
                    url_prefix: {
                        en: "graphql-tests"
                        fr: "tests-graphql"
                    }
                    entry_layout_id: "'. $newEntryLayout->data->createEntryLayout->_id .'"
                ) {
                    _id
                    title
                    handle
                    url_prefix {
                        fr
                        en
                    }
                    entry_layout_id
                }
            }
        ', [], $_ENV['test-token']);

        $newEntry = $this->client->run('
            mutation {
                createEntry(
                    entry_type_handle: "tests-graphql"
                    locale: "en"
                    is_homepage: false
                    title: "It just works"
                    template: ""
                    slug: "it-just-works"
                    content: [
                        {
                            key: "float"
                            content: "1.04"
                        }
                        {
                            key: "text"
                            content: "Not empty"
                        }
                        {
                            key: "desc"
                            content: "This text contains line returns and must keep it through all the process"
                        }
                        {
                            key: "select"
                            content: "test"
                        }
                    ]
                ) {
                    errors {
                        key
                        errors
                    }
                }
            }
        ', [], $_ENV['test-token']);

        try {
            expect($newEntryLayout->status)->toBe('ok');
            expect($newEntryType->status)->toBe('ok');
            expect($newEntry->status)->toBe('ok');
        } catch (Exception $exception) {
            //print_r($exception->getMessage());
            expect(true)->toBe(false);
        }
    }
})->group('graphql');

test('Delete layout, entry type & entry', function () {
    if (isset($_ENV['test-token'])) {
        $entryType = $this->client->run('
            {
                entryType(handle: "tests-graphql") {
                    _id
                    handle
                    entry_layout_id
                }
            }
        ', [], $_ENV['test-token']);
        $entryType = $entryType->data->entryType;

        $entry = $this->client->run('
            {
                entries(
                    entry_type_handle: "tests-graphql"
                ) {
                    list {
                        _id
                    }
                }
            }
        ', [], $_ENV['test-token']);

        $deleteEntry = $this->client->run('
            mutation {
                deleteEntry(entry_type_handle: "tests-graphql", id: "'. $entry->data->entries->list[0]->_id .'", soft: false)
            }
        ', [], $_ENV['test-token']);

        $deleteEntryType = $this->client->run('
            mutation {
                deleteEntryType(id: "'. $entryType->_id .'")
            }
        ', [], $_ENV['test-token']);

        $deleteEntryLayout = $this->client->run('
            mutation {
                deleteEntryLayout(id: "'. $entryType->entry_layout_id .'", soft: false)
            }
        ', [], $_ENV['test-token']);

        try {
            expect($deleteEntry->status)->toBe('ok');
            expect($deleteEntryLayout->status)->toBe('ok');
            expect($deleteEntryType->status)->toBe('ok');
        } catch (Exception $exception) {
            print_r($exception);
            expect(true)->toBe(false);
        }
    }
})->group('graphql');

test('Get a entry', function () {
    $entryResponse = $this->client->run('
        query {
            entries(entry_type_handle: "page", page: 1, limit: 1) {
                list {
                    _id
                    entry_type_id
                    parent {
                        handle
                        parent_id
                    }
                    site_id
                    locale
                    alternates {
                        locale
                        entry_id
                    }
                    is_homepage
                    trashed
                    title
                    template
                    slug
                    url
                    authors {
                        created_by
                        updated_by
                        deleted_by
                    }
                    dates {
                        created
                        updated
                        deleted
                    }
                    categories
                    content {
                        key
                        content
                        handle
                        type
                    }
                    schema {
                        key
                        fieldConfigs {
                            labels {
                                en
                                fr
                            }
                            handle
                            inputSettings {
                                inputKey
                                settings {
                                    name
                                    value
                                    type
                                }
                            }
                        }
                    }
                    seo {
                        entry_seo_id
                        title
                        alternates {
                            locale
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

})->group('graphql');