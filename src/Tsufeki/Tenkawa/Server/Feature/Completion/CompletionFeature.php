<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\Completion;

use Psr\Log\LoggerInterface;
use Tsufeki\BlancheJsonRpc\Dispatcher\MethodProvider;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Document\DocumentStore;
use Tsufeki\Tenkawa\Server\Feature\Capabilities\ClientCapabilities;
use Tsufeki\Tenkawa\Server\Feature\Capabilities\CompletionOptions;
use Tsufeki\Tenkawa\Server\Feature\Capabilities\ServerCapabilities;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;
use Tsufeki\Tenkawa\Server\Feature\Common\TextDocumentIdentifier;
use Tsufeki\Tenkawa\Server\Feature\Feature;
use Tsufeki\Tenkawa\Server\Utils\Stopwatch;

class CompletionFeature implements Feature, MethodProvider
{
    /**
     * @var CompletionProvider[]
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
     * @param CompletionProvider[] $providers
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
            $serverCapabilities->completionProvider = new CompletionOptions();
            $serverCapabilities->completionProvider->triggerCharacters = $this->getTriggerCharacters();
        }

        return;
        yield;
    }

    public function getRequests(): array
    {
        return [
            'textDocument/completion' => 'completion',
        ];
    }

    public function getNotifications(): array
    {
        return [];
    }

    /**
     * The completion request is sent from the client to the server to compute
     * completion items at a given cursor position.
     *
     * Completion items are presented in the IntelliSense user interface. If
     * computing full completion items is expensive, servers can additionally
     * provide a handler for the completion item resolve request
     * (‘completionItem/resolve’).
     *
     * @param TextDocumentIdentifier $textDocument The text document.
     * @param Position               $position     The position inside the text document.
     * @param CompletionContext      $context      The completion context. This is only available it the client
     *                                             specifies to send this using
     *                                             `ClientCapabilities.textDocument.completion.contextSupport === true`
     *
     * @resolve CompletionItem[]|CompletionList|null If a CompletionItem[] is provided it is interpreted to be complete.
     */
    public function completion(
        TextDocumentIdentifier $textDocument,
        Position $position,
        ?CompletionContext $context = null
    ): \Generator {
        $time = new Stopwatch();

        $document = $this->documentStore->get($textDocument->uri);
        $completions = yield $this->getCompletions($document, $position, $context);
        $count = count($completions->items);

        $this->logger->debug(__FUNCTION__ . " $textDocument->uri$position [$time, $count items]");

        return $completions;
    }

    /**
     * @resolve CompletionList
     */
    private function getCompletions(
        Document $document,
        Position $position,
        ?CompletionContext $context
    ): \Generator {
        $completions = new CompletionList();

        $completionsLists = yield array_map(function (CompletionProvider $provider) use ($document, $position, $context) {
            return $provider->getCompletions($document, $position, $context);
        }, $this->providers);

        $completions->items = array_merge(...array_map(function (CompletionList $list) {
            return $list->items;
        }, $completionsLists));

        $completions->isIncomplete = 0 !== array_sum(array_map(function (CompletionList $list) {
            return $list->isIncomplete;
        }, $completionsLists));

        return $completions;
    }

    /**
     * @return string[]
     */
    private function getTriggerCharacters(): array
    {
        return array_values(array_unique(array_merge(...array_map(function (CompletionProvider $provider) {
            return $provider->getTriggerCharacters();
        }, $this->providers))));
    }
}
