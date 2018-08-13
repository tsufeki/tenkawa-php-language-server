<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\Hover;

use PhpParser\Node;
use Tsufeki\Tenkawa\Php\Feature\NodeFinder;
use Tsufeki\Tenkawa\Php\TypeInference\Type;
use Tsufeki\Tenkawa\Php\TypeInference\TypeInference;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;
use Tsufeki\Tenkawa\Server\Feature\Hover\Hover;
use Tsufeki\Tenkawa\Server\Feature\Hover\HoverProvider;
use Tsufeki\Tenkawa\Server\Utils\PositionUtils;

class ExpressionTypeHoverProvider implements HoverProvider
{
    /**
     * @var TypeInference
     */
    private $typeInference;

    /**
     * @var NodeFinder
     */
    private $nodeFinder;

    /**
     * @var HoverFormatter
     */
    private $formatter;

    public function __construct(TypeInference $typeInference, NodeFinder $nodeFinder, HoverFormatter $formatter)
    {
        $this->typeInference = $typeInference;
        $this->nodeFinder = $nodeFinder;
        $this->formatter = $formatter;
    }

    public function getHover(Document $document, Position $position): \Generator
    {
        if ($document->getLanguage() !== 'php') {
            return null;
        }

        /** @var (Node|Comment)[] $nodes */
        $nodes = yield $this->nodeFinder->getNodePath($document, $position);
        yield $this->typeInference->infer($document);

        if (!empty($nodes) && $nodes[0] instanceof Node\Name) {
            array_shift($nodes);
        }

        if (!empty($nodes) && $nodes[0] instanceof Node) {
            $type = $nodes[0]->getAttribute('type', null);
            if ($type !== null) {
                $hover = new Hover();
                $hover->contents = $this->formatter->formatExpression($type);
                $hover->range = PositionUtils::rangeFromNodeAttrs($nodes[0]->getAttributes(), $document);

                return $hover;
            }
        }

        return null;
    }
}
