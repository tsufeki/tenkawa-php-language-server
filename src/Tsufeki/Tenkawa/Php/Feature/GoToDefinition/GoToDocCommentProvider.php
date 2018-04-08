<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\GoToDefinition;

use PhpParser\Comment;
use PhpParser\Node;
use Tsufeki\Tenkawa\Php\Feature\DocCommentHelper;
use Tsufeki\Tenkawa\Php\Feature\NodeFinder;
use Tsufeki\Tenkawa\Php\Reflection\Element\Element;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;
use Tsufeki\Tenkawa\Server\Feature\GoToDefinition\GoToDefinitionProvider;

class GoToDocCommentProvider implements GoToDefinitionProvider
{
    /**
     * @var DocCommentHelper
     */
    private $docCommentHelper;

    /**
     * @var NodeFinder
     */
    private $nodeFinder;

    public function __construct(DocCommentHelper $docCommentHelper, NodeFinder $nodeFinder)
    {
        $this->docCommentHelper = $docCommentHelper;
        $this->nodeFinder = $nodeFinder;
    }

    public function getLocations(Document $document, Position $position): \Generator
    {
        if ($document->getLanguage() !== 'php') {
            return [];
        }

        /** @var (Node|Comment)[] $nodes */
        $nodes = yield $this->nodeFinder->getNodePath($document, $position);
        /** @var Element[] $elements */
        $elements = yield $this->docCommentHelper->getReflectionFromNodePath($nodes, $document, $position);

        return array_values(array_filter(array_map(function (Element $element) {
            return $element->location;
        }, $elements)));
    }
}
