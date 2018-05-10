<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\TypeInference;

use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Utils\Cache;

interface TypeInference
{
    public function infer(Document $document, Cache $cache = null): \Generator;
}
