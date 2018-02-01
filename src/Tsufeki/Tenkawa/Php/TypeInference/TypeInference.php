<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\TypeInference;

use Tsufeki\Tenkawa\Server\Document\Document;

interface TypeInference
{
    public function infer(Document $document): \Generator;
}
