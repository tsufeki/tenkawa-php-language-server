<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Exception;

use Tsufeki\BlancheJsonRpc\Exception\JsonRpcException;

class ProjectNotOpenException extends JsonRpcException
{
    const CODE_MIN = 1002;
    const CODE_MAX = 1002;
    const MESSAGE = 'Project is not open';
}
