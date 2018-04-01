<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Exception;

use Tsufeki\BlancheJsonRpc\Exception\JsonRpcException;

class UnknownCommandException extends JsonRpcException
{
    const CODE_MIN = 1003;
    const CODE_MAX = 1003;
    const MESSAGE = 'Unknown command';

    public function __construct(string $command)
    {
        parent::__construct(self::MESSAGE . ": $command");
    }
}
