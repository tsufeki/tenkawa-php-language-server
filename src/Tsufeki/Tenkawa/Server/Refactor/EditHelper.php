<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Refactor;

use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;
use Tsufeki\Tenkawa\Server\Feature\Common\Range;
use Tsufeki\Tenkawa\Server\Feature\Common\TextEdit;
use Tsufeki\Tenkawa\Server\Utils\PositionUtils;
use Tsufeki\Tenkawa\Server\Utils\StringUtils;

class EditHelper
{
    public function getLine(Document $document, int $lineNo): string
    {
        $text = $document->getText();
        $start = new Position();
        $start->line = $lineNo;
        $start->character = 0;
        $startOffset = PositionUtils::offsetFromPosition($start, $document);
        $end = $start;
        $end->line++;
        $endOffset = PositionUtils::offsetFromPosition($end, $document);

        return substr($text, $startOffset, $endOffset - $startOffset);
    }

    public function getIndentForLine(Document $document, Position $position): Indent
    {
        $line = $this->getLine($document, $position->line);
        $indent = new Indent();

        $i = 0;
        for (; isset($line[$i]) && $line[$i] === "\t"; $i++) {
            $indent->tabs++;
        }
        for (; isset($line[$i]) && $line[$i] === ' '; $i++) {
            $indent->spaces++;
        }

        return $indent;
    }

    public function getIndentForRange(Document $document, Range $range): Indent
    {
        $position = clone $range->start;
        $indent = null;
        for (; $position->line <= $range->end->line; $position->line++) {
            $lineIndent = $this->getIndentForLine($document, $position);
            if ($indent === null) {
                $indent = $lineIndent;
            } else {
                $indent = Indent::min($indent, $lineIndent);
            }
        }
        assert($indent !== null);

        return $indent;
    }

    /**
     * @param string[] $lines Without newline characters.
     */
    public function insertLines(Document $document, int $beforeLineNo, array $lines): TextEdit
    {
        $newline = "\n"; //TODO
        $edit = new TextEdit();
        $position = PositionUtils::fixPosition(new Position($beforeLineNo, 0), $document);
        $edit->range = new Range($position, clone $position);
        $edit->newText = implode($newline, $lines) . $newline;

        if ($position->character !== 0) {
            $prevLine = $this->getLine($document, $position->line);
            if (!StringUtils::endsWith($prevLine, $newline)) {
                // Last line and it has no newline character
                $edit->newText = $newline . $edit->newText;
            }
        }

        return $edit;
    }
}
