<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\Registration;

use Psr\Log\LoggerInterface;
use Tsufeki\BlancheJsonRpc\MappedJsonRpc;
use Tsufeki\Tenkawa\Server\Feature\Capabilities\ClientCapabilities;
use Tsufeki\Tenkawa\Server\Feature\Capabilities\ServerCapabilities;
use Tsufeki\Tenkawa\Server\Feature\Feature;

class RegistrationFeature implements Feature
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
     * The client/registerCapability request is sent from the server to the
     * client to register for a new capability on the client side.
     *
     * Not all clients need to support dynamic capability registration. A client
     * opts in via the dynamicRegistration property on the specific client
     * capabilities. A client can even provide dynamic registration for
     * capability A but not for capability B (see
     * TextDocumentClientCapabilities as an example).
     *
     * @param Registration[] $registrations
     *
     * @resolve Unregistration[]
     */
    public function registerCapability(array $registrations): \Generator
    {
        $unregistrations = [];
        foreach ($registrations as $registration) {
            $registration->id = $registration->id ?? $this->generateId();
            $unregistration = new Unregistration();
            $unregistration->id = $registration->id;
            $unregistration->method = $registration->method;
            $unregistrations[] = $unregistration;
        }

        $this->logger->debug('send: ' . __FUNCTION__);
        yield $this->rpc->call('client/registerCapability', compact('registrations'));

        return $unregistrations;
    }

    /**
     * The client/unregisterCapability request is sent from the server to the
     * client to unregister a previously registered capability.
     *
     * @param Unregistration[] $unregisterations
     */
    public function unregisterCapability(array $unregisterations): \Generator
    {
        $this->logger->debug('send: ' . __FUNCTION__);
        yield $this->rpc->call('client/unregisterCapability', compact('unregisterations'));
    }

    private function generateId(): string
    {
        return uniqid('', true);
    }
}
