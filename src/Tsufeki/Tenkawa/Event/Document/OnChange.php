<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Event\Document;

use Tsufeki\Tenkawa\Document\Document;

interface OnChange
{
    public function onChange(Document $document): \Generator;
}
