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
        $entry->sourceUri = Uri::fromString('file:///dir/foo');
        $entry->key = 'foobar';
        $entry->category = 'cat1';
        $entry->data = [1, 2, 3];
        $entries[] = $entry;

        return $entries;
    }

    private function getStorageWithEntries(): \Generator
    {
        $storage = $this->getStorage();
        yield $storage->replaceFile(Uri::fromString('file:///dir/foo'), $this->getEntries(), 123456);

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
            [null, null, Query::FULL, Uri::fromString('file:///dir/foo')],
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
            [null, null, Query::FULL, Uri::fromString('file:///dir/bar')],
        ];
    }

    public function test_replaces()
    {
        ReactKernel::start(function (): \Generator {
            /** @var WritableIndexStorage $storage */
            $storage = yield $this->getStorageWithEntries();

            yield $storage->replaceFile(Uri::fromString('file:///dir/foo'), []);

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

            yield $storage->replaceFile(Uri::fromString('file:///dir/bar'), []);

            $query = new Query();
            $query->category = 'cat1';
            $query->key = 'foobar';
            $result = yield $storage->search($query);

            $this->assertCount(1, $result);
        });
    }

    /**
     * @dataProvider data_get_files
     */
    public function test_get_files(string $filterUri = null, array $expected)
    {
        ReactKernel::start(function () use ($filterUri, $expected): \Generator {
            /** @var WritableIndexStorage $storage */
            $storage = yield $this->getStorageWithEntries();

            $entry = new IndexEntry();
            $entry->sourceUri = Uri::fromString('file:///qux/foo');
            $entry->key = 'qux';
            $entry->category = 'cat2';
            yield $storage->replaceFile(Uri::fromString('file:///qux/foo'), [$entry], 234567);

            $result = yield $storage->getFileTimestamps($filterUri !== null ? Uri::fromString($filterUri) : null);
            ksort($result);

            $this->assertSame($expected, $result);
        });
    }

    public function data_get_files(): array
    {
        return [
            [null, ['file:///dir/foo' => 123456, 'file:///qux/foo' => 234567]],
            ['file:///', ['file:///dir/foo' => 123456, 'file:///qux/foo' => 234567]],
            ['file:///dir', ['file:///dir/foo' => 123456]],
            ['file:///qux', ['file:///qux/foo' => 234567]],
            ['file:///dir/foo', ['file:///dir/foo' => 123456]],
            ['file:///qux/foo', ['file:///qux/foo' => 234567]],
            ['file:///baz', []],
        ];
    }
}
