<?php

use SailCMS\Database\Model;

include_once __DIR__ . '/mock/db.php';

class DbTest extends Model
{
    public function __construct()
    {
        parent::__construct('tests');
    }

    public function fields(bool $fetchAllFields = false): array
    {
        return ['_id', 'version'];
    }

    public function getV1(): dbTest
    {
        return $this->findOne(['version' => '1.0.0'])->exec();
    }

    public function getV2(): ?dbTest
    {
        return $this->findOne(['version' => '2.0.0'])->exec();
    }

    public function writeTest()
    {
        $this->insert(['version' => '1.0.0']);
    }

    public function updateTest()
    {
        $this->updateOne(['version' => '1.0.0'], ['$set' => ['version' => '2.0.0']]);
    }

    public function deleteTest()
    {
        $this->deleteOne(['version' => '2.0.0']);
    }
}

test('Write an entry to db', function ()
{
    $model = new DbTest();
    $model->writeTest();
    $rec = $model->getV1();

    expect($rec)->not->toBeNull();
});

test('Update an entry from db', function ()
{
    $model = new DbTest();
    $model->updateTest();
    $rec = $model->getV2();

    expect($rec)->not->toBe(null)->and($rec->version)->toBe('2.0.0');
});

test('Delete an entry from db', function ()
{
    $model = new DbTest();
    $model->deleteTest();
    $rec = $model->getV2();

    expect($rec)->toBeNull();
});