<?php

use SailCMS\Search;

include_once __DIR__ . '/mock/meili.php';

beforeEach(function ()
{
    Search::registerSystemAdapters();

    try {
        $config = json_decode(file_get_contents(__DIR__ . '/mock/conf.json'), false, 512, JSON_THROW_ON_ERROR);

        if (empty($config->meili)) {
            try {
                $json = json_decode(file_get_contents(__DIR__ . '/mock/search/movies.json'), false, 512, JSON_THROW_ON_ERROR);

                echo count($json);

                $this->search = new Search();
                Search::getAdapter()->addMockData($json);
                $config->meili = true;

                file_put_contents(__DIR__ . '/mock/conf.json', json_encode($config, JSON_THROW_ON_ERROR));
            } catch (JsonException $e) {
                print_r($e);
                die();
            }
        }
    } catch (JsonException $e) {
        print_r($e);
        die();
    }
});

test('Initialize Data', function ()
{
    expect(true)->toBe(true);
});

test('Search for "Avengers" (more than 10 results)', function ()
{
    $search = new Search();
    $result = $search->search('Avengers');
    expect($result->count)->toBeGreaterThan(10);
});

test('Search for "Ultron" (less than 10 results)', function ()
{
    $search = new Search();
    $result = $search->search('Ultron');
    expect($result->count)->toBeLessThan(10);
});