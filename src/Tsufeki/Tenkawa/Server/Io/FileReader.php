<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Io;

use Tsufeki\Tenkawa\Server\Uri;

interface FileReader
{
    /**
     * @resolve string
     */
    public function read(Uri $uri): \Generator;
}
