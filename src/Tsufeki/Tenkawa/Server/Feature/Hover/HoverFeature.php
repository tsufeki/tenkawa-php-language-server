<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\Hover;

use Psr\Log\LoggerInterface;
use Tsufeki\BlancheJsonRpc\Dispatcher\MethodProvider;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Document\DocumentStore;
use Tsufeki\Tenkawa\Server\Feature\Capabilities\ClientCapabilities;
use Tsufeki\Tenkawa\Server\Feature\Capabilities\ServerCapabilities;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;
use Tsufeki\Tenkawa\Server\Feature\Common\TextDocumentIdentifier;
use Tsufeki\Tenkawa\Server\Feature\Feature;
use Tsufeki\Tenkawa\Server\Feature\Server\TextDocument\Hover;
use Tsufeki\Tenkawa\Server\Utils\Stopwatch;

class HoverFeature implements Feature, MethodProvider
{
    /**
     * @var HoverProvider[]
     */
    private $providers;

    /**
     * @var DocumentStore
     */
    private $documentStore;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param HoverProvider[] $providers
     */
    public function __construct(array $providers, DocumentStore $documentStore, LoggerInterface $logger)
    {
        $this->providers = $providers;
        $this->documentStore = $documentStore;
        $this->logger = $logger;
    }

    public function initialize(ClientCapabilities $clientCapabilities, ServerCapabilities $serverCapabilities): \Generator
    {
        $serverCapabilities->hoverProvider = !empty($this->providers);

        return;
        yield;
    }

    public function getRequests(): array
    {
        return [
            'textDocument/hover' => 'hover',
        ];
    }

    public function getNotifications(): array
    {
        return [];
    }

    /**
     * The hover request is sent from the client to the server to request hover
     * information at a given text document position.
     *
     * @param TextDocumentIdentifier $textDocument The text document.
     * @param Position               $position     The position inside the text document.
     *
     * @resolve Hover|null
     */
    public function hover(TextDocumentIdentifier $textDocument, Position $position): \Generator
    {
        $time = new Stopwatch();

        $document = $this->documentStore->get($textDocument->uri);
        $hover = null;
        foreach ($this->providers as $provider) {
            $hover = yield $provider->getHover($document, $position);
            if ($hover !== null) {
                break;
            }
        }

        $found = $hover ? 'found' : 'not found';
        $this->logger->debug(__FUNCTION__ . " $textDocument->uri$position [$time, $found]");

        return $hover;
    }
}
