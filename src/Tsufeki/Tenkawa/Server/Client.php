<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server;

use Psr\Log\LoggerInterface;
use Tsufeki\BlancheJsonRpc\MappedJsonRpc;
use Tsufeki\Tenkawa\Server\Protocol\LanguageClient;

class Client extends LanguageClient
{
    /**
     * @var MappedJsonRpc
     */
    private $rpc;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(MappedJsonRpc $rpc, LoggerInterface $logger)
    {
        $this->rpc = $rpc;
        $this->logger = $logger;
    }

    public function registerCapability(array $registrations): \Generator
    {
        $this->logger->debug('send: ' . __FUNCTION__);
        yield $this->rpc->call('client/registerCapability', compact('registrations'));
    }

    public function unregisterCapability(array $unregisterations): \Generator
    {
        $this->logger->debug('send: ' . __FUNCTION__);
        yield $this->rpc->call('client/unregisterCapability', compact('unregisterations'));
    }

    public function publishDiagnostics(Uri $uri, array $diagnostics): \Generator
    {
        $this->logger->debug('send: ' . __FUNCTION__ . " $uri");
        yield $this->rpc->notify('textDocument/publishDiagnostics', compact('uri', 'diagnostics'));
    }

    public function logMessage(int $type, string $message): \Generator
    {
        $this->logger->debug('send: ' . __FUNCTION__ . " $message");
        yield $this->rpc->notify('window/logMessage', compact('type', 'message'));
    }
}
