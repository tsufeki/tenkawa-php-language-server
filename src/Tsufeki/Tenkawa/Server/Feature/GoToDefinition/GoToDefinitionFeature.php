<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\GoToDefinition;

use Psr\Log\LoggerInterface;
use Tsufeki\BlancheJsonRpc\Dispatcher\MethodProvider;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Document\DocumentStore;
use Tsufeki\Tenkawa\Server\Feature\Capabilities\ClientCapabilities;
use Tsufeki\Tenkawa\Server\Feature\Capabilities\ServerCapabilities;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;
use Tsufeki\Tenkawa\Server\Feature\Common\TextDocumentIdentifier;
use Tsufeki\Tenkawa\Server\Feature\Feature;
use Tsufeki\Tenkawa\Server\Utils\Stopwatch;

class GoToDefinitionFeature implements Feature, MethodProvider
{
    /**
     * @var GoToDefinitionProvider[]
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
     * @param GoToDefinitionProvider[] $providers
     */
    public function __construct(array $providers, DocumentStore $documentStore, LoggerInterface $logger)
    {
        $this->providers = $providers;
        $this->documentStore = $documentStore;
        $this->logger = $logger;
    }

    public function initialize(ClientCapabilities $clientCapabilities, ServerCapabilities $serverCapabilities): \Generator
    {
        $serverCapabilities->definitionProvider = !empty($this->providers);

        return;
        yield;
    }

    public function getRequests(): array
    {
        return [
            'textDocument/definition' => 'definition',
        ];
    }

    public function getNotifications(): array
    {
        return [];
    }

    /**
     * The goto definition request is sent from the client to the server to
     * resolve the definition location of a symbol at a given text document
     * position.
     *
     * @param TextDocumentIdentifier $textDocument The text document.
     * @param Position               $position     The position inside the text document.
     *
     * @resolve Location|Location[]|null
     */
    public function definition(TextDocumentIdentifier $textDocument, Position $position): \Generator
    {
        $time = new Stopwatch();

        $document = $this->documentStore->get($textDocument->uri);
        $locations = array_merge(
            ...yield array_map(function (GoToDefinitionProvider $provider) use ($document, $position) {
                return $provider->getLocations($document, $position);
            }, $this->providers)
        );

        $count = count($locations);
        $this->logger->debug(__FUNCTION__ . " $textDocument->uri$position [$time, $count items]");

        if ($count === 0) {
            return null;
        }

        if ($count === 1) {
            return $locations[0];
        }

        return $locations;
    }
}
