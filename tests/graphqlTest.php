<?php

use SailCMS\Test\GraphQLClient;


beforeEach(function () use (&$token) {
    $graphqlUrl = env('GRAPHQL_URL');
    if ($graphqlUrl) {
        $client = new GraphQLClient($graphqlUrl);

        $authTmpResponse = $client->run('
            query ($email: String!, $password: String!){
                authenticate(email: $email, password: $password) {
                    message
                }
            }
        ', ['email' => 'philippe@leeroy.ca', 'password' => 'Hell0W0rld!']);

        $tmpToken = $authTmpResponse->data->authenticate->message;

        $authResponse = $client->run('
            query ($token: String!) {
                verifyAuthenticationToken(token: $token) {
                    auth_token
                    _id
                }
            }
        ', ['token' => $tmpToken]);

        $this->token = $tmpToken;
        print_r($tmpToken . PHP_EOL);
        print_r($authResponse);
    }
});

test('Token exist', function () {
    expect($this->token)->not()->toBeNull();
});

//test('Authenticate a user and ', function () use (&$token) {

//        $entryResponse = $client->run('
//            query {
//                entries(entry_type_handle: "page") {
//                    list {
//                        _id
//                    }
//                }
//            }
//        ', []);

//        $response = $client->run('
//            mutation {
//              entry(
//                entry_id: "' . $entry->_id . '"
//                title: "Another page seo"
//                description: "This is a description"
//                keywords: "Sail, CMS"
//                robots: true
//                sitemap: true
//              )
//            }
//        ', []);

//        if ($response->status === 'ok') {
//            print_r($response);
//        }
//    } else {
//        expect(true)->toBe(true);
//    }
//})->group('graphql');