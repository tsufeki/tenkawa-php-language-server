<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\Registration;

/**
 * General parameters to unregister a capability.
 */
class Unregistration
{
    /**
     * The id used to unregister the request or notification. Usually an id
     * provided during the register request.
     *
     * @var string
     */
    public $id;

    /**
     * The method / capability to unregister for.
     *
     * @var string
     */
    public $method;
}
