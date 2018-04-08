<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\Hover;

use PhpParser\Comment;
use PhpParser\Node;
use Tsufeki\Tenkawa\Php\Feature\GlobalsHelper;
use Tsufeki\Tenkawa\Php\Feature\NodeFinder;
use Tsufeki\Tenkawa\Php\Reflection\Element\Element;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\MarkupContent;
use Tsufeki\Tenkawa\Server\Feature\Common\MarkupKind;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;
use Tsufeki\Tenkawa\Server\Feature\Hover\Hover;
use Tsufeki\Tenkawa\Server\Feature\Hover\HoverProvider;
use Tsufeki\Tenkawa\Server\Utils\PositionUtils;

class HoverGlobalsProvider implements HoverProvider
{
    /**
     * @var GlobalsHelper
     */
    private $globalsHelper;

    /**
     * @var NodeFinder
     */
    private $nodeFinder;

    /**
     * @var HoverFormatter
     */
    private $formatter;

    public function __construct(GlobalsHelper $globalsHelper, HoverFormatter $formatter, NodeFinder $nodeFinder)
    {
        $this->globalsHelper = $globalsHelper;
        $this->formatter = $formatter;
        $this->nodeFinder = $nodeFinder;
    }

    public function getHover(Document $document, Position $position): \Generator
    {
        if ($document->getLanguage() !== 'php') {
            return null;
        }

        /** @var (Node|Comment)[] $nodes */
        $nodes = yield $this->nodeFinder->getNodePath($document, $position);
        /** @var Element[] $elements */
        $elements = yield $this->globalsHelper->getReflectionFromNodePath($nodes, $document);

        if (empty($elements)) {
            return null;
        }

        assert($nodes[0] instanceof Node);
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
