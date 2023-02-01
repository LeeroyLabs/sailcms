<?php

namespace SailCMS\Test;

use SailCMS\Test\Response\GraphQLResponse;

class GraphQLClient
{
    public function __construct(private readonly string $url)
    {
    }

    /**
     *
     * Execute a Query and get the return data
     *
     * @param string $query
     * @param array $variables
     * @param string $token
     * @return object
     * @throws \JsonException
     *
     */
    public function run(string $query, array $variables, string $token = ''): object
    {
        $ch = curl_init();

        $headers = [
            'Content-Type: application/json',
            'x-access-token: ' . $token
        ];

        $query = ['query' => $query, 'variables' => $variables];

        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($query, JSON_THROW_ON_ERROR));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $data = curl_exec($ch);
        $error = curl_error($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        // Handle errors and 404 not found
        if ($error || $info['http_code'] != "200") {
            $errorMsg = !empty($error) ? $error : $info['http_code'];
            return new GraphQLResponse('failed', $errorMsg, null);
        }

        $json = json_decode($data, false, 512, JSON_THROW_ON_ERROR);

        if (!isset($json->data)) {
            return new GraphQLResponse('failed', "Error thrown", (object)$json->errors);
        }

        return new GraphQLResponse('ok', '', $json->data);
    }
}