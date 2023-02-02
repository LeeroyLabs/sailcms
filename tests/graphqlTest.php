<?php

use SailCMS\Test\GraphQLClient;


beforeEach(function () use (&$token) {
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

        $this->token = $authResponse->data->verifyAuthenticationToken->auth_token;
    }
});

test('Get a page and modify his SEO', function () use (&$token) {
    if ($this->token) {
        $entryResponse = $this->client->run('
            query {
                entries(entry_type_handle: "page") {
                    list {
                        _id
                    }
                }
            }
        ', [], $this->token);

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
              )
            }
        ', [], $this->token);

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
                        }
                    }
                }
            ', [], $this->token);

            $entrySeo = $checkResponse->data->entry->seo;

            expect($entrySeo->title)->toBe("Another page seo")
                ->and($entrySeo->description)->toBe("This is a description")
                ->and($entrySeo->keywords)->toBe("Sail, CMS")
                ->and($entrySeo->robots)->toBeTrue()
                ->and($entrySeo->sitemap)->toBeTrue();
        } else {
            expect(true)->toBe(false);
        }
    } else {
        expect(true)->toBe(true);
    }
})->group('graphql');