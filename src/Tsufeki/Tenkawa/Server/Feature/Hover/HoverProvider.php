<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\Hover;

use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;
use Tsufeki\Tenkawa\Server\Feature\Server\TextDocument\Hover;

interface HoverProvider
{
    /**
     * @resolve Hover|null
     */
    public function getHover(Document $document, Position $position): \Generator;
}
