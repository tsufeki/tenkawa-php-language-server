<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Index;

use Tsufeki\Tenkawa\Document\Document;

interface IndexDataProvider
{
    /**
     * @resolve IndexEntry[]
     */
    public function getEntries(Document $document): \Generator;
}
