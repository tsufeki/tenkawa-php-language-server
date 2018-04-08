<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\Capabilities;

class WorkspaceFoldersServerCapabilities
{
    /**
     * The server has support for workspace folders.
     *
     * @var bool|null
     */
    public $supported;

    /**
     * Whether the server wants to receive workspace folder change notifications.
     *
     * If a strings is provided the string is treated as a ID under which the
     * notification is registed on the client side. The ID can be used to
     * unregister for these events using the `client/unregisterCapability`
     * request.
     *
     * @var string|bool|null
     */
    public $changeNotifications;
}
