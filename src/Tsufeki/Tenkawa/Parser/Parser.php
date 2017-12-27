<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Parser;

use Tsufeki\Tenkawa\Document\Document;

interface Parser
{
    /**
     * @resolve Ast
     */
    public function parse(Document $document): \Generator;
}
