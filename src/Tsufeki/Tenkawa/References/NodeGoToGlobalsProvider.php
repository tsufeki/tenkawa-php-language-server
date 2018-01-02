<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\References;

use Tsufeki\Tenkawa\Document\Document;
use Tsufeki\Tenkawa\Protocol\Common\Position;
use Tsufeki\Tenkawa\Reflection\Element\Element;

class NodeGoToGlobalsProvider implements GoToDefinitionProvider
{
    /**
     * @var NodeHelper
     */
    private $nodeHelper;

    public function __construct(NodeHelper $nodeHelper)
    {
        $this->nodeHelper = $nodeHelper;
    }

    public function getLocations(Document $document, Position $position, array $nodes): \Generator
    {
        $elements = yield $this->nodeHelper->getReflectionFromNodePath($nodes, $document);

        return array_filter(array_map(function (Element $element) {
            return $element->location;
        }, $elements));
    }
}
