<?php declare(strict_types=1);

namespace Tests\Tsufeki\Tenkawa\Server\Index\Storage;

use PHPUnit\Framework\TestCase;
use Recoil\React\ReactKernel;
use Tsufeki\Tenkawa\Server\Index\IndexEntry;
use Tsufeki\Tenkawa\Server\Index\Query;
use Tsufeki\Tenkawa\Server\Index\Storage\WritableIndexStorage;
use Tsufeki\Tenkawa\Server\Uri;

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
    public function test_finds($category, $key, $match, $uri)
    {
        ReactKernel::start(function () use ($category, $key, $match, $uri): \Generator {
            $query = new Query();
            $query->category = $category;
            $query->key = $key;
            $query->match = $match;
            $query->uri = $uri;

            /** @var WritableIndexStorage $storage */
            $storage = yield $this->getStorageWithEntries();

            $result = yield $storage->search($query);

            $this->assertEquals($this->getEntries(), $result);
        });
    }

    public function data_finds(): array
    {
        return [
            ['cat1', 'foobar', Query::FULL, null],
            [null, 'foo', Query::PREFIX, null],
            [null, 'bar', Query::SUFFIX, null],
            [null, null, Query::FULL, Uri::fromString('file:///foo')],
        ];
    }

    /**
     * @dataProvider data_doesnt_find
     */
    public function test_doesnt_find($category, $key, $match, $uri)
    {
        ReactKernel::start(function () use ($category, $key, $match, $uri): \Generator {
            $query = new Query();
            $query->category = $category;
            $query->key = $key;
            $query->match = $match;
            $query->uri = $uri;

            /** @var WritableIndexStorage $storage */
            $storage = yield $this->getStorageWithEntries();

            $result = yield $storage->search($query);

            $this->assertSame([], $result);
        });
    }

    public function data_doesnt_find(): array
    {
        return [
            ['cat2', 'foobar', Query::FULL, null],
            [null, 'foobaz', Query::FULL, null],
            [null, 'fox', Query::PREFIX, null],
            [null, 'baz', Query::SUFFIX, null],
            [null, null, Query::FULL, Uri::fromString('file:///bar')],
        ];
    }

    public function test_replaces()
    {
        ReactKernel::start(function (): \Generator {
            /** @var WritableIndexStorage $storage */
            $storage = yield $this->getStorageWithEntries();

            yield $storage->replaceFile(Uri::fromString('file:///foo'), []);

            $query = new Query();
            $query->category = 'cat1';
            $query->key = 'foobar';
            $result = yield $storage->search($query);

            $this->assertSame([], $result);
        });
    }

    public function test_doesnt_replace()
    {
        ReactKernel::start(function (): \Generator {
            /** @var WritableIndexStorage $storage */
            $storage = yield $this->getStorageWithEntries();

            yield $storage->replaceFile(Uri::fromString('file:///bar'), []);

            $query = new Query();
            $query->category = 'cat1';
            $query->key = 'foobar';
            $result = yield $storage->search($query);

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
