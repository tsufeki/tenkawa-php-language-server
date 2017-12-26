<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Event\Document;

use Tsufeki\Tenkawa\Document\Document;

interface OnOpen
{
    public function onOpen(Document $document): \Generator;
}
