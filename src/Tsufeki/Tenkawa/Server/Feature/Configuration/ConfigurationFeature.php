<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\Configuration;

use Psr\Log\LoggerInterface;
use Tsufeki\BlancheJsonRpc\Dispatcher\MethodProvider;
use Tsufeki\BlancheJsonRpc\MappedJsonRpc;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Event\OnInit;
use Tsufeki\Tenkawa\Server\Feature\Capabilities\ClientCapabilities;
use Tsufeki\Tenkawa\Server\Feature\Capabilities\ServerCapabilities;
use Tsufeki\Tenkawa\Server\Feature\Feature;
use Tsufeki\Tenkawa\Server\Feature\Registration\Registration;
use Tsufeki\Tenkawa\Server\Feature\Registration\RegistrationFeature;
use Tsufeki\Tenkawa\Server\Uri;

class ConfigurationFeature implements Feature, MethodProvider, OnInit
{
    /**
     * @var RegistrationFeature
     */
    private $registrationFeature;

    /**
     * @var MappedJsonRpc
     */
    private $rpc;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var string
     */
    private $rootKey;

    /**
     * @var bool
     */
    private $supportsDidChangeConfigurationNotification = false;

    /**
     * @var bool
     */
    private $supportsConfigurationRequest = false;

    /**
     * @var int
     */
    private $time = 0;

    /**
     * @var mixed
     */
    private $globals;

    /**
     * @var int|null
     */
    private $globalsTime;

    /**
     * @var mixed
     */
    private $defaults;

    private const VALUES_KEY = 'configuration.values';
    private const TIME_KEY = 'configuration.time';

    public function __construct(
        RegistrationFeature $registrationFeature,
        MappedJsonRpc $rpc,
        LoggerInterface $logger
    ) {
        $this->registrationFeature = $registrationFeature;
        $this->rpc = $rpc;
        $this->logger = $logger;
        $this->rootKey = 'tenkawaphp';
    }

    public function initialize(ClientCapabilities $clientCapabilities, ServerCapabilities $serverCapabilities): \Generator
    {
        $this->supportsDidChangeConfigurationNotification = $clientCapabilities->workspace &&
            $clientCapabilities->workspace->didChangeConfiguration &&
            $clientCapabilities->workspace->didChangeConfiguration->dynamicRegistration;
        $this->supportsConfigurationRequest = $clientCapabilities->workspace &&
            $clientCapabilities->workspace->configuration;

        return;
        yield;
    }

    public function getRequests(): array
    {
        return [];
    }

    public function getNotifications(): array
    {
        return [
            'workspace/didChangeConfiguration' => 'didChangeConfiguration',
        ];
    }

    /**
     * @param string $key Configuration key, possibly with multiple dot-separated parts.
     *
     * @resolve mixed|null
     */
    public function get(string $key, ?Document $document = null): \Generator
    {
        $value = null;

        if ($document !== null) {
            $value = $this->extract($key, yield $this->getForDocument($document));
        }

        if ($value === null) {
            $value = $this->extract($key, yield $this->getGlobals());
        }

        if ($value === null) {
            $value = $this->extract($key, $this->defaults);
        }

        $this->logger->debug("Configuration get: $key=" . var_export($value, true));

        return $value;
    }

    private function getForDocument(Document $document): \Generator
    {
        if ($this->time !== $document->get(self::TIME_KEY)) {
            $item = new ConfigurationItem();
            $item->scopeUri = (string)$document->getUri();
            $item->section = $this->rootKey;
            $value = (yield $this->getConfiguration([$item]))[0] ?? null;
            $document->set(self::TIME_KEY, $this->time);
            $document->set(self::VALUES_KEY, $value);
        }

        return $document->get(self::VALUES_KEY);
    }

    private function getGlobals(): \Generator
    {
        if ($this->time !== $this->globalsTime) {
            $item = new ConfigurationItem();
            $item->section = $this->rootKey;
            $this->globals = (yield $this->getConfiguration([$item]))[0] ?? null;
            $this->globalsTime = $this->time;
        }

        return $this->globals;
    }

    private function extract(string $key, $value)
    {
        foreach (explode('.', $key) as $keyPart) {
            $value = $value->$keyPart ?? null;
        }

        return $value;
    }

    public function setDefaults($defaults): void
    {
        $this->defaults = $defaults->{$this->rootKey} ?? null;
    }

    public function onInit(): \Generator
    {
        if ($this->supportsDidChangeConfigurationNotification) {
            $registration = new Registration();
            $registration->method = 'workspace/didChangeConfiguration';
            yield $this->registrationFeature->registerCapability([$registration]);
        }
    }

    /**
     * A notification sent from the client to the server to signal the change
     * of configuration settings.
     *
     * @param mixed|null $settings The actual changed settings
     */
    public function didChangeConfiguration($settings = null): \Generator
    {
        $this->time++;
        $this->logger->debug(__FUNCTION__);

        return;
        yield;
    }

    /**
     * The workspace/configuration request is sent from the server to the
     * client to fetch configuration settings from the client.
     *
     * The request can fetch several configuration settings in one roundtrip.
     * The order of the returned configuration settings correspond to the order
     * of the passed ConfigurationItems (e.g. the first item in the response is
     * the result for the first configuration item in the params).
     *
     * A ConfigurationItem consists of the configuration section to ask for and
     * an additional scope URI. The configuration section ask for is defined by
     * the server and doesn’t necessarily need to correspond to the
     * configuration store used be the client. So a server might ask for a
     * configuration cpp.formatterOptions but the client stores the
     * configuration in a XML store layout differently. It is up to the client
     * to do the necessary conversion. If a scope URI is provided the client
     * should return the setting scoped to the provided resource. If the client
     * for example uses EditorConfig to manage its settings the configuration
     * should be returned for the passed resource URI. If the client can’t
     * provide a configuration setting for a given scope then null need to be
     * present in the returned array.
     *
     * @param ConfigurationItem[] $items
     *
     * @resolve array
     */
    private function getConfiguration(array $items): \Generator
    {
        if (!$this->supportsConfigurationRequest) {
            return [];
        }

        $this->logger->debug('send: ' . __FUNCTION__);

        return yield $this->rpc->call('workspace/configuration', compact('items'), 'mixed');
    }
}
