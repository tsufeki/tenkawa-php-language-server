<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Refactoring;

use cogpowered\FineDiff\Granularity\Word;
use cogpowered\FineDiff\Parser\Operations\Copy;
use cogpowered\FineDiff\Parser\Operations\Insert;
use cogpowered\FineDiff\Parser\Operations\OperationInterface;
use cogpowered\FineDiff\Parser\Operations\Replace;
use cogpowered\FineDiff\Parser\Parser;
use Tsufeki\Tenkawa\Server\Feature\Common\Range;
use Tsufeki\Tenkawa\Server\Feature\Common\TextEdit;
use Tsufeki\Tenkawa\Server\Utils\PositionUtils;

class FineDiffer implements Differ
{
    /**
     * @var Parser
     */
    private $diff;

    public function __construct()
    {
        $this->diff = new class(new Word()) extends Parser {
            public function getEdits()
            {
                return $this->edits;
            }
        };
    }

    public function diff(string $from, string $to): \Generator
    {
        $this->diff->parse($from, $to);
        assert(method_exists($this->diff, 'getEdits'));

        $edits = [];
        $offset = 0;
        $makeNew = true;

        /** @var OperationInterface $op */
        foreach ($this->diff->getEdits() as $op) {
            if ($op instanceof Copy) {
                $makeNew = true;
            } else {
                if ($makeNew) {
                    $edits[] = [$offset, $offset, ''];
                    $makeNew = false;
                }

                $edit = &$edits[count($edits) - 1];
                $edit[1] += $op->getFromLen();
                $edit[2] .= ($op instanceof Insert || $op instanceof Replace) ? $op->getText() : '';
            }

            $offset += $op->getFromLen();
        }

        return array_map(function (array $edit) use ($from) {
            $textEdit = new TextEdit();
            $textEdit->newText = $edit[2];
            $textEdit->range = new Range(
                PositionUtils::positionFromOffset($edit[0], $from),
                PositionUtils::positionFromOffset($edit[1], $from)
            );

            return $textEdit;
        }, $edits);
        yield;
    }
}
