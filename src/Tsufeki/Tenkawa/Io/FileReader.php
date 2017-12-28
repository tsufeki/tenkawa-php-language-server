<?php

namespace Tsufeki\Tenkawa\Io;

use Tsufeki\Tenkawa\Uri;

interface FileReader
{
    /**
     * @resolve string
     */
    public function read(Uri $uri): \Generator;
}