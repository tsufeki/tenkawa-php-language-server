<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Exception;

use Tsufeki\BlancheJsonRpc\Exception\JsonRpcException;
use Tsufeki\Tenkawa\Server\Uri;

class DocumentNotOpenException extends JsonRpcException
{
    const CODE_MIN = 1001;
    const CODE_MAX = 1001;
    const MESSAGE = 'Document is not open';

    public function __construct(Uri $uri)
    {
        parent::__construct(self::MESSAGE . ": $uri");
    }
}
