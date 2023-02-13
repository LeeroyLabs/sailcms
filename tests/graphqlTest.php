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

// TODO Must create a page

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
                ->and($entrySeo->sitemap)->toBeTrue(); // TODO correct that
//                ->and($entrySeo->social_metas[0]->handle)->toBe('facebook')
//                ->and($entrySeo->social_metas[0]->content[0]->content)->toBe('Another page seo')
//                ->and($entrySeo->social_metas[0]->content[3]->name)->toBe('app_id')
//                ->and($entrySeo->social_metas[0]->content[4]->content)->toBe('article')
//                ->and($entrySeo->social_metas[1]->content[1]->content)->toBe('This is a description')
//                ->and($entrySeo->social_metas[1]->handle)->toBe('twitter')
//                ->and($entrySeo->social_metas[1]->content[3]->name)->toBe('card')
//                ->and($entrySeo->social_metas[1]->content[4]->content)->toBe('Fake image');
        } else {
            expect(true)->toBe(false);
        }
    }
})->group('graphql');