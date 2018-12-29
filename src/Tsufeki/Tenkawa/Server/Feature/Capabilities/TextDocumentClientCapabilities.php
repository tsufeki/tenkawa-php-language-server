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

    /**
     * Capabilities specific to the `textDocument/definition`.
     *
     * @var GoToClientCapabilities|null
     */
    public $definition;

    /**
     * Capabilities specific to the `textDocument/implementation`.
     *
     * @var GoToClientCapabilities|null
     */
    public $implementation;
}
