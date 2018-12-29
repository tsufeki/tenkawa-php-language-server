<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\Capabilities;

class GoToClientCapabilities extends DynamicRegistrationCapability
{
    /**
     * The client supports additional metadata in the form of links.
     *
     * @var bool|null
     */
    public $linkSupport;
}
