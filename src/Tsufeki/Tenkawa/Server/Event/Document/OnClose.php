<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Event\Document;

use Tsufeki\Tenkawa\Server\Document\Document;

interface OnClose
{
    public function onClose(Document $document): \Generator;
}
