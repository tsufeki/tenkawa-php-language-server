<?php declare(strict_types=1);

namespace Tests\Tsufeki\Tenkawa\Server\Index\Storage;

use Tsufeki\Tenkawa\Server\Index\Storage\MemoryStorage;
use Tsufeki\Tenkawa\Server\Index\Storage\WritableIndexStorage;

/**
 * @covers \Tsufeki\Tenkawa\Server\Index\Storage\MemoryStorage
 */
class MemoryStorageTest extends WritableIndexStorageTest
{
    protected function getStorage(): WritableIndexStorage
    {
        return new MemoryStorage();
    }
}
