<?php

use MongoDB\BSON\ObjectId;
use SailCMS\Database\Database;
use SailCMS\Database\Model;
use SailCMS\Models\Config;
use SailCMS\Sail;

include_once __DIR__ . '/mock/db.php';

/**
 *
 * @property string $test_key
 * @property int    $inc1
 * @property int    $inc2
 * @property array  $list
 * @property array  $list2
 *
 */
class DBTest extends Model
{
    protected string $collection = 'db_test';

    public function getAll(): array
    {
        return $this->find()->exec();
    }

    public function getByText(string $text): ?DBTest
    {
        return $this->findOne(['test_key' => $text])->exec();
    }
}

beforeAll(function ()
{
    Sail::setupForTests(__DIR__);
});

test('QuickUpdate using string field and boolean value', function ()
{
    $data = Config::getByName('testconf');
    $config = new Config();
    $config->quickUpdate($data->_id, 'test', false);
    $data = Config::getByName('testconf');

    expect($data)->not->toBeNull()->and($data->test)->toBeFalse();
    $config->quickUpdate($data->_id, 'test', true);
})->group('db');

test('QuickUpdate using array field and array value', function ()
{
    $data = Config::getByName('testconf');
    $config = new Config();
    $c = ['test' => true];

    $config->quickUpdate($data->_id, ['test', 'config'], [false, $c]);
    $data = Config::getByName('testconf');

    expect($data)->not->toBeNull()->and($data->test)->toBeFalse()->and($data->config->test)->toBeTrue();

    $c = ['test' => false];
    $config->quickUpdate($data->_id, ['test', 'config'], [true, $c]);
})->group('db');

test('QuickUpdate using array field but boolean value, expect failure', function ()
{
    $data = Config::getByName('testconf');
    $config = new Config();

    try {
        $config->quickUpdate($data->_id, ['test', 'config'], false);
        expect(false)->toBeTrue();
    } catch (\SailCMS\Errors\DatabaseException $e) {
        // Should fail!
        expect(true)->toBeTrue();
    }
})->group('db');

test('Ensure object id for given array using ensureObjectIds', function ()
{
    $config = new Config();
    $ids = $config->ensureObjectIds(['6372a9d21a182a6c5f02a988', '6372a9d21a182a6c5f02a988', '6372a9d21a182a6c5f02a988']);

    expect($ids->length)->toBe(3)->and($ids->at(0))->toBeInstanceOf(ObjectId::class);
})->group('db');

test('ActiveRecord: create a record', function ()
{
    $dbtest = new DBTest();
    $dbtest->test_key = 'hello world!';
    $dbtest->inc1 = 0;
    $dbtest->inc2 = -5;
    $dbtest->list = [];
    $dbtest->list2 = [1, 2, 2, 3, 4, 5, 6];

    expect($dbtest->isDirty())->toBeTrue()->and($dbtest->exists())->toBeFalse();
    $dbtest->save();

    expect($dbtest->exists())->toBeTrue()->and($dbtest->id)->not->toBe('');
})->group('db');

test('ActiveRecord: update a record', function ()
{
    $dbtest = new DBTest();
    $record = $dbtest->getByText('hello world!');

    $record->test_key = 'Hell0 W0rld!';
    $record->save();

    $record = $dbtest->getByText('Hell0 W0rld!');
    expect($record)->not->toBeNull();
})->group('db');

test('ActiveRecord: increment 2 fields (test multi increments)', function ()
{
    $dbtest = new DBTest();
    $record = $dbtest->getByText('Hell0 W0rld!');

    $record->increment('inc1', 5);
    $record->increment('inc2', 5);
    $record->save();
    $record->refresh();

    expect($record->inc1)->toBe(5)->and($record->inc2)->toBe(0);
})->group('db');

test('ActiveRecord: push into array', function ()
{
    $dbtest = new DBTest();
    $record = $dbtest->getByText('Hell0 W0rld!');

    $record->pushEach('list', [['test' => 1], ['test2' => 2], ['test3' => 3]]);
    $record->save();
    $record->refresh();

    expect(count($record->list))->toBe(3);
})->group('db');

test('ActiveRecord: pop out of array', function ()
{
    $dbtest = new DBTest();
    $record = $dbtest->getByText('Hell0 W0rld!');

    $record->pop('list');
    $record->save();
    $record->refresh();

    expect(count($record->list))->toBe(2);
})->group('db');

test('ActiveRecord: pull all 1 and 2s out of array', function ()
{
    $dbtest = new DBTest();
    $record = $dbtest->getByText('Hell0 W0rld!');

    $record->pullAll('list2', [1, 2]);
    $record->save();
    $record->refresh();

    expect(count($record->list2))->toBe(4);
})->group('db');

test('ActiveRecord: get record by id', function ()
{
    $dbtest = new DBTest();
    $list = $dbtest->getAll();
    $test = DBTest::get($list[0]->id);
    expect($test)->not->toBeNull();
})->group('db');

test('ActiveRecord: get record by field (using ==)', function ()
{
    $test = DBTest::getBy('test_key', 'Hell0 W0rld!');
    expect($test)->not->toBeNull();
})->group('db');

test('ActiveRecord: get record by field and fail (using !=)', function ()
{
    $test = DBTest::getBy('test_key', 'Hell0 W0rld!', '!=');
    expect($test)->toBeNull();
})->group('db');

test('ActiveRecord: delete record', function ()
{
    $dbtest = new DBTest();
    $test = $dbtest->getByText('Hell0 W0rld!');
    $test->remove();

    $test = $dbtest->getByText('Hell0 W0rld!');
    expect($test)->toBeNull();
})->group('db');

test('Dump database', function ()
{
    $dumpReturn = (new Database())->databaseDump('sailcms');
    expect($dumpReturn)->toBeTrue();
})->group('db');


test('ActiveRecord: nested value change and check for dirty', function ()
{
    $row = DBTest::get('6503351a02767c3c4bfc6d9a');

    if ($row) {
        $row->toplevel->nested = true;
        $row->setDirty('toplevel');
        $row->save();
    }

    expect(true)->toBeTrue();
})->group('db');