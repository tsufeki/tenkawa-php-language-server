<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Io\FileWatcher;

use Recoil\Recoil;
use Tsufeki\Tenkawa\Server\Event\EventDispatcher;
use Tsufeki\Tenkawa\Server\Event\OnFileChange;
use Tsufeki\Tenkawa\Server\Uri;

class FileChangeDeduplicator
{
    /**
     * @var EventDispatcher
     */
    private $eventDispatcher;

    /**
     * @var float
     */
    private $accumulateTime;

    /**
     * @var array<string,Uri>
     */
    private $uris = [];

    /**
     * @var bool
     */
    private $accumulating = false;

    public function __construct(EventDispatcher $eventDispatcher, float $accumulateTime)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->accumulateTime = $accumulateTime;
    }

    /**
     * @param Uri[] $uris
     */
    public function dispatch(array $uris): \Generator
    {
        foreach ($uris as $uri) {
            $this->uris[$uri->getNormalizedWithSlash()] = $uri;
        }

        if (!$this->accumulating) {
            $this->accumulating = true;

            try {
                yield Recoil::sleep($this->accumulateTime);
                $this->deduplicate();
                yield $this->eventDispatcher->dispatch(OnFileChange::class, array_values($this->uris));
            } finally {
                $this->uris = [];
                $this->accumulating = false;
            }
        }
    }

    private function deduplicate(): void
    {
        ksort($this->uris);
        /** @var Uri|null $prevUri */
        $prevUri = null;
        foreach ($this->uris as $key => $uri) {
            if ($prevUri !== null && ($prevUri->equals($uri) || $prevUri->isParentOf($uri))) {
                unset($this->uris[$key]);
            } else {
                $prevUri = $uri;
            }
        }
    }
}
