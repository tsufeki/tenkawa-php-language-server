<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Utils;

use Tsufeki\Tenkawa\Document\Document;
use Tsufeki\Tenkawa\Protocol\Common\Position;
use Tsufeki\Tenkawa\Protocol\Common\Range;

class PositionUtils
{
    public static function positionFromOffset(int $offset, Document $document): Position
    {
        $text = $document->getText();
        $offset = max(0, min($offset, strlen($text) - 1));

        $line = -1;
        $currentOffset = 0;
        $lastOffset = 0;

        while ($currentOffset <= $offset) {
            $lastOffset = $currentOffset;
            $currentOffset = strpos($text, "\n", $currentOffset);
            $line++;
            if ($currentOffset === false) {
                break;
            }
            $currentOffset++; // newline character
        }

        $column = max(0, $offset - $lastOffset);
        // Protocol defines column number as UTF-16 code unit count
        $textBefore = substr($text, $lastOffset, $column);
        $columnUtf16 = strlen(mb_convert_encoding($textBefore, 'UTF-16LE', 'UTF-8')) >> 1;

        return new Position($line, $columnUtf16);
    }

    public static function rangeFromNodeAttrs(array $attributes, Document $document): Range
    {
        return new Range(
            self::positionFromOffset($attributes['startFilePos'] ?? 0, $document),
            self::positionFromOffset(($attributes['endFilePos'] ?? 0) + 1, $document)
        );
    }
}
