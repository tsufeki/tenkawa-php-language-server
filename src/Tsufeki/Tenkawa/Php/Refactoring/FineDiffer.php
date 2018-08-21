<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Refactoring;

use cogpowered\FineDiff\Granularity\Word;
use cogpowered\FineDiff\Parser\Operations\OperationInterface;
use cogpowered\FineDiff\Parser\Parser;

class FineDiffer
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

        return array_map(function (OperationInterface $op) {
        }, $this->diff->getEdits());
        yield;
    }
}
