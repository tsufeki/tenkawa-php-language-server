<?php declare(strict_types=1);

namespace Tests\Tsufeki\Tenkawa\Index\Storage;

use Tsufeki\Tenkawa\Index\Storage\IndexStorage;
use Tsufeki\Tenkawa\Index\Storage\SqliteStorage;

/**
 * @covers \Tsufeki\Tenkawa\Index\Storage\SqliteStorage
 */
class SqliteStorageTest extends IndexStorageTest
{
    protected function getStorage(): IndexStorage
    {
        return new SqliteStorage(':memory:');
    }
}
