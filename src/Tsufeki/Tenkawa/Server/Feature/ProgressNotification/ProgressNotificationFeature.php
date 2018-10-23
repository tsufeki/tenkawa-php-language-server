<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\ProgressNotification;

use Recoil\Kernel;
use Tsufeki\BlancheJsonRpc\MappedJsonRpc;
use Tsufeki\Tenkawa\Server\Feature\Capabilities\ClientCapabilities;
use Tsufeki\Tenkawa\Server\Feature\Capabilities\ServerCapabilities;
use Tsufeki\Tenkawa\Server\Feature\Feature;

/**
 * Custom protocol extension for progress notifications.
 */
class ProgressNotificationFeature implements Feature
{
    /**
     * @var MappedJsonRpc|null
     */
    private $rpc;

    /**
     * @var Kernel
     */
    private $kernel;

    public function __construct(?MappedJsonRpc $rpc = null, Kernel $kernel)
    {
        $this->rpc = $rpc;
        $this->kernel = $kernel;
    }

    public function initialize(ClientCapabilities $clientCapabilities, ServerCapabilities $serverCapabilities): \Generator
    {
        return;
        yield;
    }

    public function create(): ProgressGroup
    {
        $callback = function (string $id, ?string $label = null, ?int $status = null, bool $done = false) {
            $this->kernel->execute($this->progress($id, $label, $status, $done));
        };

        return new ProgressGroup($this->generateId(), $callback);
    }

    private function progress(string $id, ?string $label = null, ?int $status = null, bool $done = false): \Generator
    {
        if ($this->rpc !== null) {
            yield $this->rpc->notify('$/tenkawaphp/window/progress', compact('id', 'label', 'status', 'done'));
        }
    }

    private function generateId(): string
    {
        return uniqid('', true);
    }
}
