<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\ProgressNotification;

use Recoil\Recoil;
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
     * @var MappedJsonRpc
     */
    private $rpc;

    public function __construct(MappedJsonRpc $rpc)
    {
        $this->rpc = $rpc;
    }

    public function initialize(ClientCapabilities $clientCapabilities, ServerCapabilities $serverCapabilities): \Generator
    {
        return;
        yield;
    }

    /**
     * @resolve Progress
     */
    public function create(): \Generator
    {
        $callback = yield Recoil::callback(\Closure::fromCallable([$this, 'progress']));

        return new Progress($this->generateId(), $callback);
    }

    private function progress(string $id, ?string $label = null, ?int $status = null, bool $done = false): \Generator
    {
        yield $this->rpc->notify('$/tenkawaphp/window/progress', compact('id', 'label', 'status', 'done'));
    }

    private function generateId(): string
    {
        return uniqid('', true);
    }
}
