<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Language;

use PhpParser\Comment;
use PhpParser\Node;
use Tsufeki\Tenkawa\Php\Reflection\Element\Element;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Language\HoverProvider;
use Tsufeki\Tenkawa\Server\Protocol\Common\MarkupContent;
use Tsufeki\Tenkawa\Server\Protocol\Common\MarkupKind;
use Tsufeki\Tenkawa\Server\Protocol\Common\Position;
use Tsufeki\Tenkawa\Server\Protocol\Server\TextDocument\Hover;

class HoverDocCommentProvider implements HoverProvider
{
    /**
     * @var DocCommentHelper
     */
    private $docCommentHelper;

    /**
     * @var NodeFinder
     */
    private $nodeFinder;

    /**
     * @var HoverFormatter
     */
    private $formatter;

    public function __construct(DocCommentHelper $docCommentHelper, HoverFormatter $formatter, NodeFinder $nodeFinder)
    {
        $this->docCommentHelper = $docCommentHelper;
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
        $elements = yield $this->docCommentHelper->getReflectionFromNodePath($nodes, $document, $position);

        if (empty($elements)) {
            return null;
        }

        $hover = new Hover();
        // TODO check client capabilities
        // $hover->contents = new MarkupContent();
        // $hover->contents->kind = MarkupKind::MARKDOWN;
        // $hover->contents->string = $this->formatter->format($elements[0]);
        $hover->contents = $this->formatter->format($elements[0]);

        return $hover;
    }
}
