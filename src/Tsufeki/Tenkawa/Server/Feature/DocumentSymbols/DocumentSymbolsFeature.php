<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\DocumentSymbols;

use Psr\Log\LoggerInterface;
use Tsufeki\BlancheJsonRpc\Dispatcher\MethodProvider;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Document\DocumentStore;
use Tsufeki\Tenkawa\Server\Feature\Capabilities\ClientCapabilities;
use Tsufeki\Tenkawa\Server\Feature\Capabilities\ServerCapabilities;
use Tsufeki\Tenkawa\Server\Feature\Common\SymbolInformation;
use Tsufeki\Tenkawa\Server\Feature\Common\TextDocumentIdentifier;
use Tsufeki\Tenkawa\Server\Feature\Feature;
use Tsufeki\Tenkawa\Server\Utils\Stopwatch;

class DocumentSymbolsFeature implements Feature, MethodProvider
{
    /**
     * @var DocumentSymbolsProvider[]
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
     * @param DocumentSymbolsProvider[] $providers
     */
    public function __construct(array $providers, DocumentStore $documentStore, LoggerInterface $logger)
    {
        $this->providers = $providers;
        $this->documentStore = $documentStore;
        $this->logger = $logger;
    }

    public function initialize(ClientCapabilities $clientCapabilities, ServerCapabilities $serverCapabilities): \Generator
    {
        $serverCapabilities->documentSymbolProvider = !empty($this->providers);

        return;
        yield;
    }

    public function getRequests(): array
    {
        return [
            'textDocument/documentSymbol' => 'documentSymbol',
        ];
    }

    public function getNotifications(): array
    {
        return [];
    }

    /**
     * The document symbol request is sent from the client to the server to
     * return a flat list of all symbols found in a given text document.
     *
     * Neither the document’s location range nor the documents container name
     * should be used to infer a hierarchy.
     *
     * @param TextDocumentIdentifier $textDocument The text document.
     *
     * @resolve SymbolInformation[]|null
     */
    public function documentSymbol(TextDocumentIdentifier $textDocument): \Generator
    {
        $time = new Stopwatch();

        $document = $this->documentStore->get($textDocument->uri);
        $symbols = array_merge(
            ...yield array_map(function (DocumentSymbolsProvider $provider) use ($document) {
                return $provider->getSymbols($document);
            }, $this->providers)
        );

        $count = count($symbols);
        $this->logger->debug(__FUNCTION__ . " $textDocument->uri [$time, $count items]");

        return $symbols;
    }
}
