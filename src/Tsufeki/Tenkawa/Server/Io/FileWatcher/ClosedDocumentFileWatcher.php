<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Io\FileWatcher;

use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Event\Document\OnClose;
use Tsufeki\Tenkawa\Server\Event\EventDispatcher;
use Tsufeki\Tenkawa\Server\Event\OnFileChange;
use Tsufeki\Tenkawa\Server\Uri;

class ClosedDocumentFileWatcher implements FileWatcher, OnClose
{
    /**
     * @var EventDispatcher
     */
    private $eventDispatcher;

    public function __construct(EventDispatcher $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    public function onClose(Document $document): \Generator
    {
        yield $this->eventDispatcher->dispatch(OnFileChange::class, [$document->getUri()]);
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function start(): \Generator
    {
        return;
        yield;
    }

    public function stop(): \Generator
    {
        return;
        yield;
    }

    public function addDirectory(Uri $uri): \Generator
    {
        return;
        yield;
    }

    public function removeDirectory(Uri $uri): \Generator
    {
        return;
        yield;
    }
}
