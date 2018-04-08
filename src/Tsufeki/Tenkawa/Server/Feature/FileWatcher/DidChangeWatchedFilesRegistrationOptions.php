<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\FileWatcher;

class DidChangeWatchedFilesRegistrationOptions
{
    /**
     * The watchers to register.
     *
     * @var FileSystemWatcher[]
     */
    public $watchers;
}
