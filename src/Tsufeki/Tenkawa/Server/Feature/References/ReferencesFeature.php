<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\References;

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

class ReferencesFeature implements Feature, MethodProvider
{
    /**
     * @var ReferencesProvider[]
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
     * @param ReferencesProvider[] $providers
     */
    public function __construct(array $providers, DocumentStore $documentStore, LoggerInterface $logger)
    {
        $this->providers = $providers;
        $this->documentStore = $documentStore;
        $this->logger = $logger;
    }

    public function initialize(ClientCapabilities $clientCapabilities, ServerCapabilities $serverCapabilities): \Generator
    {
        $serverCapabilities->referencesProvider = !empty($this->providers);

        return;
        yield;
    }

    public function getRequests(): array
    {
        return [
            'textDocument/references' => 'references',
        ];
    }

    public function getNotifications(): array
    {
        return [];
    }

    /**
     * The references request is sent from the client to the server to resolve
     * project-wide references for the symbol denoted by the given text
     * document position.
     *
     * @param TextDocumentIdentifier $textDocument The text document.
     * @param Position               $position     The position inside the text document.
     * @param ReferenceContext|null  $context
     *
     * @resolve Location[]|null
     */
    public function references(
        TextDocumentIdentifier $textDocument,
        Position $position,
        ReferenceContext $context = null
    ): \Generator {
        $time = new Stopwatch();

        $document = $this->documentStore->get($textDocument->uri);
        $locations = array_merge(
            ...yield array_map(function (ReferencesProvider $provider) use ($document, $position, $context) {
                return $provider->getReferences($document, $position, $context);
            }, $this->providers)
        );

        $count = count($locations);
        $this->logger->debug(__FUNCTION__ . " $textDocument->uri$position [$time, $count items]");

        if ($count === 0) {
            return null;
        }

        return $locations;
    }
}
