<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Event\Document;

use Tsufeki\Tenkawa\Server\Document\Document;

interface OnOpen
{
    public function onOpen(Document $document): \Generator;
}
