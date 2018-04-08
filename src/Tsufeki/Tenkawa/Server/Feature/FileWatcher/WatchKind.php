<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\FileWatcher;

class WatchKind
{
    /**
     * Interested in create events.
     */
    const CREATE = 1;

    /**
     * Interested in change events
     */
    const CHANGE = 2;

    /**
     * Interested in delete events
     */
    const DELETE = 4;
}
