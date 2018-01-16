<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\References;

use PhpParser\Node;
use Tsufeki\Tenkawa\Document\Document;
use Tsufeki\Tenkawa\Protocol\Common\Position;
use Tsufeki\Tenkawa\Protocol\Server\TextDocument\Hover;
use Tsufeki\Tenkawa\TypeInference\TypeInference;
use Tsufeki\Tenkawa\Utils\PositionUtils;

class ExpressionTypeHoverProvider implements HoverProvider
{
    /**
     * @var TypeInference
     */
    private $typeInference;

    public function __construct(TypeInference $typeInference)
    {
        $this->typeInference = $typeInference;
    }

    public function getHover(Document $document, Position $position, array $nodes): \Generator
    {
        yield $this->typeInference->infer($document);

        if (!empty($nodes) && $nodes[0] instanceof Node\Name) {
            array_shift($nodes);
        }

        $type = null;
        if (!empty($nodes) && $nodes[0] instanceof Node) {
            $type = $nodes[0]->getAttribute('type', null);
        }

        if ($type !== null) {
            $hover = new Hover();
            $hover->contents = "expression: $type";
            $hover->range = PositionUtils::rangeFromNodeAttrs($nodes[0]->getAttributes(), $document);

            return $hover;
        }

        return null;
    }
}
