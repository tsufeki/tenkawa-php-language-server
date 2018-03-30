<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Index;

use Tsufeki\Tenkawa\Server\Document\Document;

interface IndexDataProvider
{
    /**
     * @resolve IndexEntry[]
     */
    public function getEntries(Document $document, string $origin = null): \Generator;

    public function getVersion(): int;
}
