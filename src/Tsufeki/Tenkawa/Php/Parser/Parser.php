<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Parser;

use Tsufeki\Tenkawa\Server\Document\Document;

interface Parser
{
    /**
     * @resolve Ast
     */
    public function parse(Document $document): \Generator;
}
