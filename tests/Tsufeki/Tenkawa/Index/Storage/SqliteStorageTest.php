<?php declare(strict_types=1);

namespace Tests\Tsufeki\Tenkawa\Index\Storage;

use Tsufeki\Tenkawa\Index\Storage\SqliteStorage;
use Tsufeki\Tenkawa\Index\Storage\WritableIndexStorage;

/**
 * @covers \Tsufeki\Tenkawa\Index\Storage\SqliteStorage
 */
class SqliteStorageTest extends WritableIndexStorageTest
{
    protected function getStorage(): WritableIndexStorage
    {
        return new SqliteStorage(':memory:');
    }
}
