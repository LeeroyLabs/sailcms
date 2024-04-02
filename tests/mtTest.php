<?php

use SailCMS\Convert;
use SailCMS\Models\Asset;
use SailCMS\Sail;

include_once __DIR__ . '/mock/db.php';

class Tester extends \SailCMS\MultiThreading\Worker
{
    private $d;

    public function initialize(mixed $data): void
    {
        $this->d = $data;
    }

    /**
     * @return bool
     */
    public function execute(): bool
    {
        file_put_contents(__DIR__ . '/mock/mt.log', json_encode($this->d) . "\n", FILE_APPEND);
        sleep(2);
        return true;
    }
}

beforeAll(function ()
{
    Sail::setupForTests(__DIR__);
});

test('Multi-Thread System', function ()
{
    $pool = new \SailCMS\WorkerPool();
    $time = time();

    for ($i = 0; $i < 10; $i++) {
        $pool->add(new Tester(), ['phrase' => 'Hello World!']);
    }

    $now = time();
    $diff = $now - $time;

    expect($diff)->toBeGreaterThanOrEqual(4);
})->group('mt');