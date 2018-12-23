<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Exception;

use Tsufeki\BlancheJsonRpc\Exception\RequestCancelledException as BaseRequestCancelledException;

class RequestCancelledException extends BaseRequestCancelledException
{
    const CODE_MIN = -32800;
    const CODE_MAX = -32800;
}
