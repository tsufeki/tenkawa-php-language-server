<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Utils;

use Tsufeki\Tenkawa\Document\Document;
use Tsufeki\Tenkawa\Protocol\Common\Position;
use Tsufeki\Tenkawa\Protocol\Common\Range;

class PositionUtils
{
    /**
     * Return line beginnings offsets.
     *
     * @return int[]
     */
    private static function getLineOffsets(Document $document): array
    {
        if (null !== $lineOffsets = $document->get('line_offsets')) {
            return $lineOffsets;
        }

        $text = $document->getText();
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
        $document->set('line_offsets', $lineOffsets);

        return $lineOffsets;
    }

    public static function positionFromOffset(int $offset, Document $document): Position
    {
        $text = $document->getText();
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

    public static function rangeFromNodeAttrs(array $attributes, Document $document): Range
    {
        return new Range(
            self::positionFromOffset($attributes['startFilePos'] ?? 0, $document),
            self::positionFromOffset(($attributes['endFilePos'] ?? 0) + 1, $document)
        );
    }
}
