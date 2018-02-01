<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Language;

use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Protocol\Common\Location;
use Tsufeki\Tenkawa\Server\Protocol\Common\Position;

interface GoToDefinitionProvider
{
    /**
     * @resolve Location[]
     */
    public function getLocations(Document $document, Position $position): \Generator;
}
