<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\FileWatcher;

class FileSystemWatcher
{
    /**
     * The  glob pattern to watch
     *
     * @var string
     */
    public $globPattern;

    /**
     * The kind of events of interest.
     *
     * If omitted it defaults to WatchKind::CREATE | WatchKind::CHANGE |
     * WatchKind::DELETE which is 7.
     *
     * @see WatchKind
     *
     * @var int|null
     */
    public $kind;
}
