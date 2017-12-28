<?php declare(strict_types=1);

namespace Tests\Tsufeki\Tenkawa\Index\Storage;

use PHPUnit\Framework\TestCase;
use Recoil\React\ReactKernel;
use Tsufeki\Tenkawa\Index\IndexEntry;
use Tsufeki\Tenkawa\Index\Storage\IndexStorage;
use Tsufeki\Tenkawa\Index\Storage\WritableIndexStorage;
use Tsufeki\Tenkawa\Uri;

abstract class WritableIndexStorageTest extends TestCase
{
    abstract protected function getStorage(): WritableIndexStorage;

    private function getEntries(): array
    {
        $entries = [];

        $entry = new IndexEntry();
        $entry->sourceUri = Uri::fromString('file:///foo');
        $entry->key = 'foobar';
        $entry->category = 'cat1';
        $entry->data = [1, 2, 3];
        $entries[] = $entry;

        return $entries;
    }

    private function getStorageWithEntries(): \Generator
    {
        $storage = $this->getStorage();
        yield $storage->replaceFile(Uri::fromString('file:///foo'), $this->getEntries(), 123456);

        return $storage;
    }

    /**
     * @dataProvider data_finds
     */
    public function test_finds($category, $key, $match)
    {
        ReactKernel::start(function () use ($category, $key, $match): \Generator {
            /** @var WritableIndexStorage $storage */
            $storage = yield $this->getStorageWithEntries();

            $result = yield $storage->search($category, $key, $match);

            $this->assertEquals($this->getEntries(), $result);
        });
    }

    public function data_finds(): array
    {
        return [
            ['cat1', 'foobar', IndexStorage::FULL],
            [null, 'foo', IndexStorage::PREFIX],
            [null, 'bar', IndexStorage::SUFFIX],
        ];
    }

    /**
     * @dataProvider data_doesnt_find
     */
    public function test_doesnt_find($category, $key, $match)
    {
        ReactKernel::start(function () use ($category, $key, $match): \Generator {
            /** @var WritableIndexStorage $storage */
            $storage = yield $this->getStorageWithEntries();

            $result = yield $storage->search($category, $key, $match);

            $this->assertSame([], $result);
        });
    }

    public function data_doesnt_find(): array
    {
        return [
            ['cat2', 'foobar', IndexStorage::FULL],
            [null, 'foobaz', IndexStorage::FULL],
            [null, 'fox', IndexStorage::PREFIX],
            [null, 'baz', IndexStorage::SUFFIX],
        ];
    }

    public function test_replaces()
    {
        ReactKernel::start(function (): \Generator {
            /** @var WritableIndexStorage $storage */
            $storage = yield $this->getStorageWithEntries();

            yield $storage->replaceFile(Uri::fromString('file:///foo'), []);
            $result = yield $storage->search('cat1', 'foobar');

            $this->assertSame([], $result);
        });
    }

    public function test_doesnt_replace()
    {
        ReactKernel::start(function (): \Generator {
            /** @var WritableIndexStorage $storage */
            $storage = yield $this->getStorageWithEntries();

            yield $storage->replaceFile(Uri::fromString('file:///bar'), []);
            $result = yield $storage->search('cat1', 'foobar');

            $this->assertCount(1, $result);
        });
    }

    public function test_get_files()
    {
        ReactKernel::start(function (): \Generator {
            /** @var WritableIndexStorage $storage */
            $storage = yield $this->getStorageWithEntries();

            $result = yield $storage->getFileTimestamps();

            $this->assertSame(['file:///foo' => 123456], $result);
        });
    }
}
