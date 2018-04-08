<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\FileWatcher;

use Tsufeki\Tenkawa\Server\Uri;

class FileEvent
{
    /**
     * The file's URI.
     *
     * @var Uri
     */
    public $uri;

    /**
     * The change type.
     *
     * @see FileChangeType
     *
     * @var int
     */
    public $type;
}
