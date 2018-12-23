<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\TypeInference;

use PhpParser\Comment;
use PhpParser\Node;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Utils\Cache;

interface TypeInference
{
    /**
     * @param (Node|Comment)[] $nodePath
     */
    public function infer(Document $document, ?array $nodePath = null, ?Cache $cache = null): \Generator;
}
