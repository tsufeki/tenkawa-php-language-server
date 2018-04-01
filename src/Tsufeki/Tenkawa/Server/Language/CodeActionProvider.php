<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Language;

use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Protocol\Common\Command;
use Tsufeki\Tenkawa\Server\Protocol\Common\Range;
use Tsufeki\Tenkawa\Server\Protocol\Server\TextDocument\CodeActionContext;

interface CodeActionProvider
{
    /**
     * @resolve Command[]
     */
    public function getCodeActions(Document $document, Range $range, CodeActionContext $context): \Generator;
}
