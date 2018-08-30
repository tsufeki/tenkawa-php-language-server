<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Utils;

use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;
use Tsufeki\Tenkawa\Server\Feature\Common\Range;

class PositionUtils
{
    /**
     * Return line beginnings offsets.
     *
     * @param Document|string $document
     *
     * @return int[]
     */
    private static function getLineOffsets($document): array
    {
        if ($document instanceof Document && null !== $lineOffsets = $document->get('line_offsets')) {
            return $lineOffsets;
        }

        $text = $document instanceof Document ? $document->getText() : $document;
        $lineOffsets = [];
        $offset = 0;

        while (true) {
            $lineOffsets[] = $offset;
            $offset = strpos($text, "\n", $offset);
            if ($offset === false) {
                break;
            }
            $offset++; // newline character
        }

        $lineOffsets[] = strlen($text);
        if ($document instanceof Document) {
            $document->set('line_offsets', $lineOffsets);
        }

        return $lineOffsets;
    }

    /**
     * @param Document|string $document
     */
    public static function positionFromOffset(int $offset, $document): Position
    {
        $text = $document instanceof Document ? $document->getText() : $document;
        $offset = max(0, min($offset, strlen($text)));

        $lineOffsets = self::getLineOffsets($document);
        $line = count($lineOffsets) - 2;
        while ($lineOffsets[$line] > $offset) {
            $line--;
        }

        $column = max(0, $offset - $lineOffsets[$line]);
        // Protocol defines column number as UTF-16 code unit count
        $textBefore = substr($text, $lineOffsets[$line], $column);
        $columnUtf16 = strlen(mb_convert_encoding($textBefore, 'UTF-16LE', 'UTF-8')) >> 1;

        return new Position($line, $columnUtf16);
    }

    public static function offsetFromPosition(Position $position, Document $document): int
    {
        $text = $document->getText();
        $lineOffsets = self::getLineOffsets($document);
        $line = max(0, min($position->line, count($lineOffsets) - 2));

        $offset = $lineOffsets[$line];
        // Protocol defines column number as UTF-16 code unit count
        $lineText = substr($text, $offset, $lineOffsets[$line + 1] - $offset);
        $lineTextUtf16 = mb_convert_encoding($lineText, 'UTF-16LE', 'UTF-8');
        $column = max(0, min($position->character, strlen($lineTextUtf16) >> 1));
        $textBeforeUtf16 = substr($lineTextUtf16, 0, $column << 1);
        $offset += strlen(mb_convert_encoding($textBeforeUtf16, 'UTF-8', 'UTF-16LE'));

        return $offset;
    }

    public static function fixPosition(Position $position, Document $document): Position
    {
        return self::positionFromOffset(self::offsetFromPosition($position, $document), $document);
    }

    public static function rangeFromNodeAttrs(array $attributes, Document $document): Range
    {
        return new Range(
            self::positionFromOffset($attributes['startFilePos'] ?? 0, $document),
            self::positionFromOffset(($attributes['endFilePos'] ?? 0) + 1, $document)
        );
    }

    public static function extractRange(Range $range, Document $document): string
    {
        $start = self::offsetFromPosition($range->start, $document);
        $end = self::offsetFromPosition($range->end, $document);
        if ($start >= $end) {
            return '';
        }

        return substr($document->getText(), $start, $end - $start);
    }

    public static function contains(Range $range, Position $position): bool
    {
        return self::compare($range->start, $position) <= 0
            && self::compare($position, $range->end) < 0;
    }

    public static function compare(Position $position1, Position $position2): int
    {
        return [$position1->line, $position1->character] <=> [$position2->line, $position2->character];
    }

    public static function move(Position $position, int $characters, Document $document): Position
    {
        return self::positionFromOffset(self::offsetFromPosition($position, $document) + $characters, $document);
    }

    public static function overlap(Range $range1, Range $range2): bool
    {
        return self::compare($range1->end, $range2->start) > 0
            && self::compare($range2->end, $range1->start) > 0;
    }

    /**
     * Check if two ranges overlap. Zero-length ranges can also overlap on start points.
     */
    public static function overlapZeroLength(Range $range1, Range $range2): bool
    {
        if ($range1->start == $range1->end) {
            $range1 = clone $range1;
            $range1->end->character++;
        }

        if ($range2->start == $range2->end) {
            $range2 = clone $range2;
            $range2->end->character++;
        }

        return self::overlap($range1, $range2);
    }
}
