<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\References;

use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\Location;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;

interface ReferencesProvider
{
    /**
     * @resolve Location[]
     */
    public function getReferences(Document $document, Position $position, ?ReferenceContext $context): \Generator;
}
