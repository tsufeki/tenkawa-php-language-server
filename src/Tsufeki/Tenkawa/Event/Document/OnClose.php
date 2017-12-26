<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Event\Document;

use Tsufeki\Tenkawa\Document\Document;

interface OnClose
{
    public function onClose(Document $document): \Generator;
}
