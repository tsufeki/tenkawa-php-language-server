<?php declare(strict_types=1);

namespace Tests\Tsufeki\Tenkawa\Index\Storage;

use Tsufeki\Tenkawa\Index\Storage\IndexStorage;
use Tsufeki\Tenkawa\Index\Storage\MemoryStorage;

/**
 * @covers \Tsufeki\Tenkawa\Index\Storage\MemoryStorage
 */
class MemoryStorageTest extends IndexStorageTest
{
    protected function getStorage(): IndexStorage
    {
        return new MemoryStorage();
    }
}
