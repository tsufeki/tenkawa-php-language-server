<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\References;

use Tsufeki\Tenkawa\Document\Document;
use Tsufeki\Tenkawa\Protocol\Common\MarkupContent;
use Tsufeki\Tenkawa\Protocol\Common\MarkupKind;
use Tsufeki\Tenkawa\Protocol\Common\Position;
use Tsufeki\Tenkawa\Protocol\Server\TextDocument\Hover;
use Tsufeki\Tenkawa\Reflection\Element\Element;
use Tsufeki\Tenkawa\Utils\PositionUtils;

class HoverMembersProvider implements HoverProvider
{
    /**
     * @var MembersHelper
     */
    private $membersHelper;

    /**
     * @var HoverFormatter
     */
    private $formatter;

    public function __construct(MembersHelper $membersHelper, HoverFormatter $formatter)
    {
        $this->membersHelper = $membersHelper;
        $this->formatter = $formatter;
    }

    public function getHover(Document $document, Position $position, array $nodes): \Generator
    {
        if ($document->getLanguage() !== 'php') {
            return [];
        }

        /** @var Element[] $elements */
        $elements = yield $this->membersHelper->getReflectionFromNodePath($nodes, $document, $position);

        if (empty($elements)) {
            return null;
        }

        $hover = new Hover();
        // TODO check client capabilities
        // $hover->contents = new MarkupContent();
        // $hover->contents->kind = MarkupKind::MARKDOWN;
        // $hover->contents->string = $this->formatter->format($elements[0]);
        $hover->contents = $this->formatter->format($elements[0]);
        $hover->range = PositionUtils::rangeFromNodeAttrs($nodes[0]->getAttributes(), $document);

        return $hover;
    }
}
