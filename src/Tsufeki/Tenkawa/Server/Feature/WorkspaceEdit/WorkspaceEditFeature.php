<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\WorkspaceEdit;

use Psr\Log\LoggerInterface;
use Tsufeki\BlancheJsonRpc\MappedJsonRpc;
use Tsufeki\Tenkawa\Server\Feature\Capabilities\ClientCapabilities;
use Tsufeki\Tenkawa\Server\Feature\Capabilities\ServerCapabilities;
use Tsufeki\Tenkawa\Server\Feature\Common\WorkspaceEdit;
use Tsufeki\Tenkawa\Server\Feature\Feature;

class WorkspaceEditFeature implements Feature
{
    /**
     * @var MappedJsonRpc
     */
    private $rpc;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        MappedJsonRpc $rpc,
        LoggerInterface $logger
    ) {
        $this->rpc = $rpc;
        $this->logger = $logger;
    }

    public function initialize(ClientCapabilities $clientCapabilities, ServerCapabilities $serverCapabilities): \Generator
    {
        return;
        yield;
    }

    /**
     * The workspace/applyEdit request is sent from the server to the client to
     * modify resource on the client side.
     *
     * @param string|null   $label An optional label of the workspace edit.
     *                             This label is presented in the user interface
     *                             for example on an undo stack to undo the workspace edit.
     * @param WorkspaceEdit $edit  The edits to apply.
     *
     * @resolve ApplyWorkspaceEditResponse
     */
    public function applyWorkspaceEdit(?string $label, WorkspaceEdit $edit): \Generator
    {
        $this->logger->debug('send: ' . __FUNCTION__);

        return yield $this->rpc->call('workspace/applyEdit', compact('label', 'edit'), ApplyWorkspaceEditResponse::class);
    }
}
