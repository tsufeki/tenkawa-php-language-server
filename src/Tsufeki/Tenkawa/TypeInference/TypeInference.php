<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\TypeInference;

use Tsufeki\Tenkawa\Document\Document;

interface TypeInference
{
    public function infer(Document $document): \Generator;
}
