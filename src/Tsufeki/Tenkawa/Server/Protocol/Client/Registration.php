<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Protocol\Client;

/**
 * General parameters to register for a capability.
 */
class Registration
{
    /**
     * The id used to register the request.
     *
     * The id can be used to deregister the request again.
     *
     * @var string
     */
    public $id;

    /**
     * The method / capability to register for.
     *
     * @var string
     */
    public $method;

    /**
     * Options necessary for the registration.
     *
     * @var mixed|null
     */
    public $registerOptions;
}
