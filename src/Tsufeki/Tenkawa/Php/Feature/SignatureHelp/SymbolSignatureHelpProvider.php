<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\SignatureHelp;

use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;
use Tsufeki\Tenkawa\Server\Feature\SignatureHelp\SignatureHelp;
use Tsufeki\Tenkawa\Server\Feature\SignatureHelp\SignatureHelpProvider;

class SymbolSignatureHelpProvider implements SignatureHelpProvider
{
    /**
     * @resolve SignatureHelp|null
     */
    public function getSignatureHelp(Document $document, Position $position): \Generator
    {
        if ($document->getLanguage() !== 'php') {
            return null;
        }

        return null;
        yield;
    }

    /**
     * @var string[]
     */
    public function getTriggerCharacters(): array
    {
        return ['(', ',', ')'];
    }
}
