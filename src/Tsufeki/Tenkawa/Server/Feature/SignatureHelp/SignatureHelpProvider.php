<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\SignatureHelp;

use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;

interface SignatureHelpProvider
{
    /**
     * @resolve SignatureHelp|null
     */
    public function getSignatureHelp(Document $document, Position $position): \Generator;

    /**
     * @var string[]
     */
    public function getTriggerCharacters(): array;
}
