<?php declare(strict_types=1);

namespace Tests\Tsufeki\Tenkawa\Server\Index\Storage;

use Tsufeki\Tenkawa\Server\Index\Storage\SqliteStorage;
use Tsufeki\Tenkawa\Server\Index\Storage\WritableIndexStorage;

/**
 * @covers \Tsufeki\Tenkawa\Server\Index\Storage\SqliteStorage
 */
class SqliteStorageWithPrefixTest extends WritableIndexStorageTest
{
    protected function getStorage(): WritableIndexStorage
    {
        return new SqliteStorage(SqliteStorage::MEMORY, '1', 'file:///dir');
    }
}
