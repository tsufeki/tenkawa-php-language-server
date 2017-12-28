<?php declare(strict_types=1);

namespace Tests\Tsufeki\Tenkawa\Index\Storage;

use Tsufeki\Tenkawa\Index\Storage\MemoryStorage;
use Tsufeki\Tenkawa\Index\Storage\WritableIndexStorage;

/**
 * @covers \Tsufeki\Tenkawa\Index\Storage\MemoryStorage
 */
class MemoryStorageTest extends WritableIndexStorageTest
{
    protected function getStorage(): WritableIndexStorage
    {
        return new MemoryStorage();
    }
}
