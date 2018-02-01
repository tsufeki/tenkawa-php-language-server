<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Language;

use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Protocol\Common\Position;
use Tsufeki\Tenkawa\Server\Protocol\Server\TextDocument\Hover;

interface HoverProvider
{
    /**
     * @resolve Hover|null
     */
    public function getHover(Document $document, Position $position): \Generator;
}
