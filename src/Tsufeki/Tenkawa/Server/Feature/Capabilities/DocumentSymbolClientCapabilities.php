<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\Capabilities;

class DocumentSymbolClientCapabilities extends DynamicRegistrationCapability
{
    /**
     * The client support hierarchical document symbols.
     *
     * @var bool|null
     */
    public $hierarchicalDocumentSymbolSupport;
}
