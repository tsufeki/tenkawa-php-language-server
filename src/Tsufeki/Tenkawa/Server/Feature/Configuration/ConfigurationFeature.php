<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\Configuration;

use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Capabilities\ClientCapabilities;
use Tsufeki\Tenkawa\Server\Feature\Capabilities\ServerCapabilities;
use Tsufeki\Tenkawa\Server\Feature\Feature;

class ConfigurationFeature implements Feature
{
    /**
     * @var string
     */
    private $rootKey;

    /**
     * @var mixed
     */
    private $globals;

    public function __construct()
    {
        $this->rootKey = 'tenkawaphp';
    }

    public function initialize(ClientCapabilities $clientCapabilities, ServerCapabilities $serverCapabilities): \Generator
    {
        return;
        yield;
    }

    /**
     * @param string $key Configuration key, possibly with multiple dot-separated parts.
     *
     * @resolve mixed|null
     */
    public function get(string $key, Document $document = null): \Generator
    {
        $value = $this->globals;
        foreach (explode('.', $key) as $keyPart) {
            $value = $value->$keyPart ?? null;
        }

        return $value;
        yield;
    }

    public function setGlobals($globals)
    {
        $this->globals = $globals->{$this->rootKey} ?? null;
    }
}
