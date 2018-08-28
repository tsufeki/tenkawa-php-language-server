<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\SignatureHelp;

use Psr\Log\LoggerInterface;
use Tsufeki\BlancheJsonRpc\Dispatcher\MethodProvider;
use Tsufeki\Tenkawa\Server\Document\DocumentStore;
use Tsufeki\Tenkawa\Server\Feature\Capabilities\ClientCapabilities;
use Tsufeki\Tenkawa\Server\Feature\Capabilities\ServerCapabilities;
use Tsufeki\Tenkawa\Server\Feature\Capabilities\SignatureHelpOptions;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;
use Tsufeki\Tenkawa\Server\Feature\Common\TextDocumentIdentifier;
use Tsufeki\Tenkawa\Server\Feature\Feature;
use Tsufeki\Tenkawa\Server\Utils\PriorityKernel\Priority;
use Tsufeki\Tenkawa\Server\Utils\Stopwatch;

class SignatureHelpFeature implements Feature, MethodProvider
{
    /**
     * @var SignatureHelpProvider[]
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
     * @param SignatureHelpProvider[] $providers
     */
    public function __construct(array $providers, DocumentStore $documentStore, LoggerInterface $logger)
    {
        $this->providers = $providers;
        $this->documentStore = $documentStore;
        $this->logger = $logger;
    }

    public function initialize(ClientCapabilities $clientCapabilities, ServerCapabilities $serverCapabilities): \Generator
    {
        if (!empty($this->providers)) {
            $serverCapabilities->signatureHelpProvider = new SignatureHelpOptions();
            $serverCapabilities->signatureHelpProvider->triggerCharacters = $this->getTriggerCharacters();
        }

        return;
        yield;
    }

    public function getRequests(): array
    {
        return [
            'textDocument/signatureHelp' => 'signatureHelp',
        ];
    }

    public function getNotifications(): array
    {
        return [];
    }

    /**
     * The signature help request is sent from the client to the server to
     * request signature information at a given cursor position.
     *
     * @param TextDocumentIdentifier $textDocument The text document.
     * @param Position               $position     The position inside the text document.
     *
     * @resolve SignatureHelp|null
     */
    public function signatureHelp(TextDocumentIdentifier $textDocument, Position $position): \Generator
    {
        $time = new Stopwatch();
        yield Priority::interactive();

        $document = $this->documentStore->get($textDocument->uri);
        $signatureHelp = null;
        foreach ($this->providers as $provider) {
            $signatureHelp = yield $provider->getSignatureHelp($document, $position);
            if ($signatureHelp !== null) {
                break;
            }
        }

        $found = $signatureHelp ? 'found' : 'not found';
        $this->logger->debug(__FUNCTION__ . " $textDocument->uri$position [$time, $found]");

        return $signatureHelp;
    }

    /**
     * @return string[]
     */
    private function getTriggerCharacters(): array
    {
        return array_values(array_unique(array_merge(...array_map(function (SignatureHelpProvider $provider) {
            return $provider->getTriggerCharacters();
        }, $this->providers))));
    }
}
