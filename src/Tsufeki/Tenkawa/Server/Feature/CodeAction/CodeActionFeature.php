<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\CodeAction;

use Psr\Log\LoggerInterface;
use Tsufeki\BlancheJsonRpc\Dispatcher\MethodProvider;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Document\DocumentStore;
use Tsufeki\Tenkawa\Server\Exception\DocumentNotOpenException;
use Tsufeki\Tenkawa\Server\Feature\Capabilities\ClientCapabilities;
use Tsufeki\Tenkawa\Server\Feature\Capabilities\ServerCapabilities;
use Tsufeki\Tenkawa\Server\Feature\Common\Range;
use Tsufeki\Tenkawa\Server\Feature\Common\TextDocumentIdentifier;
use Tsufeki\Tenkawa\Server\Feature\Feature;
use Tsufeki\Tenkawa\Server\Utils\PriorityKernel\Priority;
use Tsufeki\Tenkawa\Server\Utils\Stopwatch;

class CodeActionFeature implements Feature, MethodProvider
{
    /**
     * @var CodeActionProvider[]
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
     * @param CodeActionProvider[] $providers
     */
    public function __construct(array $providers, DocumentStore $documentStore, LoggerInterface $logger)
    {
        $this->providers = $providers;
        $this->documentStore = $documentStore;
        $this->logger = $logger;
    }

    public function initialize(ClientCapabilities $clientCapabilities, ServerCapabilities $serverCapabilities): \Generator
    {
        $serverCapabilities->codeActionProvider = !empty($this->providers);

        return;
        yield;
    }

    public function getRequests(): array
    {
        return [
            'textDocument/codeAction' => 'codeAction',
        ];
    }

    public function getNotifications(): array
    {
        return [];
    }

    /**
     * The code action request is sent from the client to the server to compute
     * commands for a given text document and range.
     *
     * These commands are typically code fixes to either fix problems or to
     * beautify/refactor code. The result of a textDocument/codeAction request
     * is an array of Command literals which are typically presented in the
     * user interface. When the command is selected the server should be
     * contacted again (via the workspace/executeCommand) request to execute
     * the command.
     *
     * @param TextDocumentIdentifier $textDocument The document in which the command was invoked.
     * @param Range                  $range        The range for which the command was invoked.
     * @param CodeActionContext      $context      Context carrying additional information.
     *
     * @resolve Command[]|null
     */
    public function codeAction(TextDocumentIdentifier $textDocument, Range $range, CodeActionContext $context): \Generator
    {
        $time = new Stopwatch();
        yield Priority::interactive(-10);

        try {
            $document = $this->documentStore->get($textDocument->uri);
            $commands = array_merge(
                ...yield array_map(function (CodeActionProvider $provider) use ($document, $range, $context) {
                    return $provider->getCodeActions($document, $range, $context);
                }, $this->providers)
            );
        } catch (DocumentNotOpenException $e) {
            // This happens on vscode when opening a file, ignore it.
            $commands = [];
        }

        $count = count($commands);
        $this->logger->debug(__FUNCTION__ . " $textDocument->uri$range->start$range->end [$time, $count items]");

        return $commands;
    }
}
