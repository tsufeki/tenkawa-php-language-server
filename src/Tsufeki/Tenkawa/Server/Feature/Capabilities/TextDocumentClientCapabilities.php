<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\Capabilities;

/**
 * Text document specific client capabilities.
 */
class TextDocumentClientCapabilities
{
    /**
     * Capabilities specific to the `textDocument/documentSymbol`
     *
     * @var DocumentSymbolClientCapabilities|null
     */
    public $documentSymbol;
}
