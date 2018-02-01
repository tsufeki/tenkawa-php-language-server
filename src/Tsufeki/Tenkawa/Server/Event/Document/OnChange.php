<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Event\Document;

use Tsufeki\Tenkawa\Server\Document\Document;

interface OnChange
{
    public function onChange(Document $document): \Generator;
}
