<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Protocol\Client;

class DidChangeWatchedFilesRegistrationOptions
{
    /**
     * The watchers to register.
     *
     * @var FileSystemWatcher[]
     */
    public $watchers;
}
