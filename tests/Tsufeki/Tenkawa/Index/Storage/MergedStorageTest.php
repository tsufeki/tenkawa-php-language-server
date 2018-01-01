<?php declare(strict_types=1);

namespace Tests\Tsufeki\Tenkawa\Index\Storage;

use PHPUnit\Framework\TestCase;
use Recoil\React\ReactKernel;
use Tsufeki\Tenkawa\Index\IndexEntry;
use Tsufeki\Tenkawa\Index\Storage\IndexStorage;
use Tsufeki\Tenkawa\Index\Storage\MergedStorage;
use Tsufeki\Tenkawa\Uri;

/**
 * @covers \Tsufeki\Tenkawa\Index\Storage\MergedStorage
 */
class MergedStorageTest extends TestCase
{
    public function test()
    {
        ReactKernel::start(function () {
            $category = 'cat';
            $key = 'key';
            $match = IndexStorage::PREFIX;

            $entries1 = [new IndexEntry(), new IndexEntry()];
            $entries1[0]->sourceUri = Uri::fromString('file:///foo');
            $entries1[1]->sourceUri = Uri::fromString('file:///bar');

            $storage1 = $this->createMock(IndexStorage::class);
            $storage1
                ->expects($this->once())
                ->method('getFileTimestamps')
                ->willReturn((function () {
                    return [
                        'file:///foo' => 121212,
                        'file:///bar' => 131313,
                    ];
                    yield;
                })());
            $storage1
                ->expects($this->once())
                ->method('search')
                ->with($this->identicalTo($category), $this->identicalTo($key), $this->identicalTo($match))
                ->willReturn((function () use ($entries1) {
                    return $entries1;
                    yield;
                })());

            $entries2 = [new IndexEntry(), new IndexEntry()];
            $entries2[0]->sourceUri = Uri::fromString('file:///bar');
            $entries2[1]->sourceUri = Uri::fromString('file:///baz');

            $storage2 = $this->createMock(IndexStorage::class);
            $storage2
                ->expects($this->once())
                ->method('getFileTimestamps')
                ->willReturn((function () {
                    return [
                        'file:///bar' => 141414,
                        'file:///baz' => 151515,
                    ];
                    yield;
                })());
            $storage2
                ->expects($this->once())
                ->method('search')
                ->with($this->identicalTo($category), $this->identicalTo($key), $this->identicalTo($match))
                ->willReturn((function () use ($entries2) {
                    return $entries2;
                    yield;
                })());

            $storage = new MergedStorage([$storage1, $storage2]);

            $this->assertSame([
                'file:///foo' => 121212,
                'file:///bar' => 141414,
                'file:///baz' => 151515,
            ], yield $storage->getFileTimestamps());

            $this->assertSame(array_merge($entries1, $entries2), yield $storage->search($category, $key, $match));
        });
    }
}
