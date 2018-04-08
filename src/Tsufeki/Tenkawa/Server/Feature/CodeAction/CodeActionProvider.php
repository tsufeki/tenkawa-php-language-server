<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\CodeAction;

use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\Command;
use Tsufeki\Tenkawa\Server\Feature\Common\Range;

interface CodeActionProvider
{
    /**
     * @resolve Command[]
     */
    public function getCodeActions(Document $document, Range $range, CodeActionContext $context): \Generator;
}
