<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\Message;

use Psr\Log\LoggerInterface;
use Tsufeki\BlancheJsonRpc\MappedJsonRpc;
use Tsufeki\Tenkawa\Server\Feature\Capabilities\ClientCapabilities;
use Tsufeki\Tenkawa\Server\Feature\Capabilities\ServerCapabilities;
use Tsufeki\Tenkawa\Server\Feature\Feature;

class MessageFeature implements Feature
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
     * The show message notification is sent from a server to a client to ask
     * the client to display a particular message in the user interface.
     *
     * @param int    $type    The message type. See MessageType.
     * @param string $message The actual message.
     */
    public function showMessage(int $type, string $message): \Generator
    {
        $this->logger->debug('send: ' . __FUNCTION__ . " $message");
        yield $this->rpc->notify('window/showMessage', compact('type', 'message'));
    }

    /**
     * The show message request is sent from a server to a client to ask the
     * client to display a particular message in the user interface.
     *
     * In addition to the show message notification the request allows to pass
     * actions and to wait for an answer from the client.
     *
     * @param int                      $type    The message type. See MessageType.
     * @param string                   $message The actual message.
     * @param MessageActionItem[]|null $actions The message action items to present.
     *
     * @resolve MessageActionItem|null
     */
    public function showMessageRequest(int $type, string $message, ?array $actions = null): \Generator
    {
        $this->logger->debug('send: ' . __FUNCTION__ . " $message");

        return yield $this->rpc->call(
            'window/showMessageRequest',
            compact('type', 'message', 'actions'),
            MessageActionItem::class . '|null'
        );
    }

    /**
     * The log message notification is sent from the server to the client to
     * ask the client to log a particular message.
     *
     * @param int    $type    The message type. See MessageType
     * @param string $message The actual message
     */
    public function logMessage(int $type, string $message): \Generator
    {
        $this->logger->debug('send: ' . __FUNCTION__ . " $message");
        yield $this->rpc->notify('window/logMessage', compact('type', 'message'));
    }
}
