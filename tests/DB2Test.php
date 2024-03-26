<?php

use Carbon\Carbon;
use MongoDB\BSON\ObjectId;
use SailCMS\Collection;
use SailCMS\Database\Database;
use SailCMS\Database\Model;
use SailCMS\Models\Config;
use SailCMS\Models\User;
use SailCMS\Sail;

include_once __DIR__ . '/mock/db.php';

class UserItem implements \SailCMS\Contracts\Castable
{
    public function __construct(public string $name = '', public string $email = '') { }

    /**
     * @return mixed
     */
    public function castFrom(): mixed
    {
        return (object)[
            'name' => $this->name,
            'email' => $this->email
        ];
    }

    /**
     * @param  mixed  $value
     * @return mixed
     */
    public function castTo(mixed $value): mixed
    {
        return new self($value->name, $value->email);
    }
}

/**
 *
 * @property string   $name
 * @property int      $timestamp
 * @property Carbon   $datetime
 * @property ObjectId $someid
 * @property string   $user_id
 * @property ?User    $user
 *
 */
class DB2Test extends Model
{
    protected string $collection = 'db_test';
    protected array $casting = [
        'timestamp' => 'timestamp',
        'datetime' => Carbon::class,
        'someid' => ObjectId::class,
        'timestamps' => [Collection::class, 'timestamp']
    ];

    public function getAll(): ?DB2Test
    {
        return $this->findOne(['_id' => $this->ensureObjectId('658056a995bda1231b841e38')])->fetch('user_id', 'user', User::class)->exec();
    }
}

beforeAll(function ()
{
    Sail::setupForTests(__DIR__);
});

test('test casting', function ()
{
    $db = new DB2Test();
    $object = $db->getAll();


    if (!empty($object)) {
        echo "HERE";
    }

    var_dump($object);
    print_r($object);
    die();
})->group('db2');