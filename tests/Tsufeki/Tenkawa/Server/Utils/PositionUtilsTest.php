<?php declare(strict_types=1);

namespace Tests\Tsufeki\Tenkawa\Server\Utils;

use PHPUnit\Framework\TestCase;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Protocol\Common\Position;
use Tsufeki\Tenkawa\Server\Protocol\Common\Range;
use Tsufeki\Tenkawa\Server\Uri;
use Tsufeki\Tenkawa\Server\Utils\PositionUtils;

/**
 * @covers \Tsufeki\Tenkawa\Server\Utils\PositionUtils
 */
class PositionUtilsTest extends TestCase
{
    /**
     * @dataProvider data_position_from_offset
     */
    public function test_position_from_offset($line, $column, $text, $offset)
    {
        $document = new Document(Uri::fromString('file:///foo'), 'php');
        $document->update($text);

        $pos = PositionUtils::positionFromOffset($offset, $document);

        $this->assertSame($line, $pos->line);
        $this->assertSame($column, $pos->character);
    }

    public function data_position_from_offset(): array
    {
        return [
            [0, 0, '', 0],

            [0, 0, 'qaz', 0],
            [0, 1, 'qaz', 1],
            [0, 2, 'qaz', 2],
            [0, 3, 'qaz', 3],

            [0, 3, "qaz\n", 3],
            [1, 0, "qaz\n", 4],

            [1, 0, "qaz\nwsx", 4],
            [1, 1, "qaz\nwsx", 5],
            [1, 3, "qaz\nwsx", 7],

            [0, 0, "\nqaz\n\nwsx\n", 0],
            [1, 0, "\nqaz\n\nwsx\n", 1],
            [1, 3, "\nqaz\n\nwsx\n", 4],
            [2, 0, "\nqaz\n\nwsx\n", 5],
            [3, 0, "\nqaz\n\nwsx\n", 6],
            [3, 3, "\nqaz\n\nwsx\n", 9],
            [4, 0, "\nqaz\n\nwsx\n", 10],

            [0, 3, "qaz\xE2\x82\xACwsx", 3],
            [0, 4, "qaz\xE2\x82\xACwsx", 6],
            [0, 5, "qaz\xE2\x82\xACwsx", 7],

            [1, 0, "\na\xF0\x90\x90\x80b", 1],
            [1, 1, "\na\xF0\x90\x90\x80b", 2],
            [1, 3, "\na\xF0\x90\x90\x80b", 6],
            [1, 4, "\na\xF0\x90\x90\x80b", 7],

            [0, 0, '', 1],
            [1, 3, "qaz\nwsx", 20],
        ];
    }

    /**
     * @dataProvider data_offset_from_position
     */
    public function test_offset_from_position($line, $column, $text, $expectedOffset)
    {
        $document = new Document(Uri::fromString('file:///foo'), 'php');
        $document->update($text);

        $offset = PositionUtils::offsetFromPosition(new Position($line, $column), $document);

        $this->assertSame($expectedOffset, $offset);
    }

    public function data_offset_from_position(): array
    {
        return [
            [0, 0, '', 0],

            [0, 0, 'qaz', 0],
            [0, 1, 'qaz', 1],
            [0, 2, 'qaz', 2],
            [0, 3, 'qaz', 3],

            [0, 3, "qaz\n", 3],
            [1, 0, "qaz\n", 4],

            [1, 0, "qaz\nwsx", 4],
            [1, 1, "qaz\nwsx", 5],
            [1, 3, "qaz\nwsx", 7],

            [0, 0, "\nqaz\n\nwsx\n", 0],
            [1, 0, "\nqaz\n\nwsx\n", 1],
            [1, 3, "\nqaz\n\nwsx\n", 4],
            [2, 0, "\nqaz\n\nwsx\n", 5],
            [3, 0, "\nqaz\n\nwsx\n", 6],
            [3, 3, "\nqaz\n\nwsx\n", 9],
            [4, 0, "\nqaz\n\nwsx\n", 10],

            [0, 3, "qaz\xE2\x82\xACwsx", 3],
            [0, 4, "qaz\xE2\x82\xACwsx", 6],
            [0, 5, "qaz\xE2\x82\xACwsx", 7],

            [1, 0, "\na\xF0\x90\x90\x80b", 1],
            [1, 1, "\na\xF0\x90\x90\x80b", 2],
            [1, 3, "\na\xF0\x90\x90\x80b", 6],
            [1, 4, "\na\xF0\x90\x90\x80b", 7],
        ];
    }

    public function test_range_from_node_attrs()
    {
        $document = new Document(Uri::fromString('file:///foo'), 'php');
        $document->update("foo\nbar");

        $range = PositionUtils::rangeFromNodeAttrs([
            'startFilePos' => 1,
            'endFilePos' => 4,
        ], $document);

        $this->assertEquals(new Range(new Position(0, 1), new Position(1, 1)), $range);
    }
}
