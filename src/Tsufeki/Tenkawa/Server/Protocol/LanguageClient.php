<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Protocol;

use Tsufeki\Tenkawa\Server\Protocol\Client\FileSystemWatcher;
use Tsufeki\Tenkawa\Server\Protocol\Client\Registration;
use Tsufeki\Tenkawa\Server\Protocol\Client\Unregistration;
use Tsufeki\Tenkawa\Server\Protocol\Common\Diagnostic;
use Tsufeki\Tenkawa\Server\Uri;

abstract class LanguageClient
{
    /**
     * Undocumented functionThe client/registerCapability request is sent from
     * the server to the client to register for a new capability on the client
     * side.
     *
     * Not all clients need to support dynamic capability registration. A client
     * opts in via the dynamicRegistration property on the specific client
     * capabilities. A client can even provide dynamic registration for
     * capability A but not for capability B (see
     * TextDocumentClientCapabilities as an example).
     *
     * method: client/registerCapability
     *
     * @param Registration[] $registrations
     */
    abstract public function registerCapability(array $registrations): \Generator;

    /**
     * The client/unregisterCapability request is sent from the server to the
     * client to unregister a previously registered capability.
     *
     * method: client/unregisterCapability
     *
     * @param Unregistration[] $unregisterations
     */
    abstract public function unregisterCapability(array $unregisterations): \Generator;

    /**
     * Register for workspace/didChangeWatchedFiles notification.
     *
     * @param FileSystemWatcher[] $watchers
     *
     * @resolve Unregistration
     */
    abstract public function registerFileSystemWatchers(array $watchers): \Generator;

    /**
     * Diagnostics notification are sent from the server to the client to
     * signal results of validation runs.
     *
     * Diagnostics are "owned" by the server so it is the server’s
     * responsibility to clear them if necessary. The following rule is used
     * for VS Code servers that generate diagnostics:
     *
     *  - if a language is single file only (for example HTML) then diagnostics
     *    are cleared by the server when the file is closed.
     *  - if a language has a project system (for example C#) diagnostics are
     *    not cleared when a file closes. When a project is opened all
     *    diagnostics for all files are recomputed (or read from a cache).
     *
     * When a file changes it is the server’s responsibility to re-compute
     * diagnostics and push them to the client. If the computed set is empty it
     * has to push the empty array to clear former diagnostics. Newly pushed
     * diagnostics always replace previously pushed diagnostics. There is no
     * merging that happens on the client side.
     *
     * method: textDocument/publishDiagnostics
     *
     * @param Uri          $uri         The URI for which diagnostic information is reported.
     * @param Diagnostic[] $diagnostics An array of diagnostic information items.
     */
    abstract public function publishDiagnostics(Uri $uri, array $diagnostics): \Generator;

    /**
     * The log message notification is sent from the server to the client to
     * ask the client to log a particular message.
     *
     * method: window/logMessage
     *
     * @param int    $type    The message type. See MessageType
     * @param string $message The actual message
     */
    abstract public function logMessage(int $type, string $message): \Generator;
}
