<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\FileWatcher;

class FileChangeType
{
    /**
     * The file got created.
     */
    const CREATED = 1;

    /**
     * The file got changed.
     */
    const CHANGED = 2;

    /**
     * The file got deleted.
     */
    const DELETED = 3;
}
