<?php declare(strict_types=1);

namespace Tests\Tsufeki\Tenkawa\Utils;

use PHPUnit\Framework\TestCase;
use Tsufeki\Tenkawa\Document\Document;
use Tsufeki\Tenkawa\Document\Project;
use Tsufeki\Tenkawa\Protocol\Common\Position;
use Tsufeki\Tenkawa\Protocol\Common\Range;
use Tsufeki\Tenkawa\Uri;
use Tsufeki\Tenkawa\Utils\PositionUtils;

/**
 * @covers \Tsufeki\Tenkawa\Utils\PositionUtils
 */
class PositionUtilsTest extends TestCase
{
    /**
     * @dataProvider data_position_from_offset
     */
    public function test_position_from_offset($line, $column, $text, $offset)
    {
        $project = new Project(Uri::fromString('file:///'));
        $document = new Document(Uri::fromString('file:///foo'), 'php', $project);
        $document->update($text);

        $pos = PositionUtils::positionFromOffset($offset, $document);

        $this->assertSame($line, $pos->line);
        $this->assertSame($column, $pos->character);
    }

    public function data_position_from_offset(): array
    {
        return [
            [0, 0, '', 0],
            [0, 0, '', 1],
            [0, 0, '', 2],

            [0, 0, 'qaz', 0],
            [0, 1, 'qaz', 1],
            [0, 2, 'qaz', 2],
            [0, 1, "qaz\n", 1],
            [0, 2, "qaz\n", 2],
            [0, 3, "qaz\n", 3],

            [0, 1, "qaz\nwsx", 1],
            [0, 3, "qaz\nwsx", 3],
            [1, 1, "qaz\nwsx", 5],
            [1, 2, "qaz\nwsx", 6],
            [1, 3, "qaz\na𐐀b", 9],

            [0, 0, "\nqaz\n\nwsx\n", 0],
            [1, 1, "\nqaz\n\nwsx\n", 2],
            [3, 2, "\nqaz\n\nwsx\n", 8],
            [3, 3, "\nqaz\n\nwsx\n", 9],
            [3, 3, "\nqaz\n\nwsx\n", 20],
        ];
    }

    /**
     * @dataProvider data_offset_from_position
     */
    public function test_offset_from_position($line, $column, $text, $expectedOffset)
    {
        $project = new Project(Uri::fromString('file:///'));
        $document = new Document(Uri::fromString('file:///foo'), 'php', $project);
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
            [0, 1, "qaz\n", 1],
            [0, 2, "qaz\n", 2],
            [0, 3, "qaz\n", 3],

            [0, 1, "qaz\nwsx", 1],
            [0, 3, "qaz\nwsx", 3],
            [1, 1, "qaz\nwsx", 5],
            [1, 2, "qaz\nwsx", 6],
            [1, 3, "qaz\na𐐀b", 9],

            [0, 0, "\nqaz\n\nwsx\n", 0],
            [1, 1, "\nqaz\n\nwsx\n", 2],
            [3, 2, "\nqaz\n\nwsx\n", 8],
            [3, 3, "\nqaz\n\nwsx\n", 9],
            [3, 9, "\nqaz\n\nwsx\n", 10],
            [9, 3, "\nqaz\n\nwsx\n", 10],
        ];
    }

    public function test_range_from_node_attrs()
    {
        $project = new Project(Uri::fromString('file:///'));
        $document = new Document(Uri::fromString('file:///foo'), 'php', $project);
        $document->update("foo\nbar");

        $range = PositionUtils::rangeFromNodeAttrs([
            'startFilePos' => 1,
            'endFilePos' => 4,
        ], $document);

        $this->assertEquals(new Range(new Position(0, 1), new Position(1, 1)), $range);
    }
}
