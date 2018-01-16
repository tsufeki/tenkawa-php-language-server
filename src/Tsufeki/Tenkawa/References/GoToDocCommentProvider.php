<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\References;

use Tsufeki\Tenkawa\Document\Document;
use Tsufeki\Tenkawa\Protocol\Common\Position;
use Tsufeki\Tenkawa\Reflection\Element\Element;

class GoToDocCommentProvider implements GoToDefinitionProvider
{
    /**
     * @var DocCommentHelper
     */
    private $docCommentHelper;

    public function __construct(DocCommentHelper $docCommentHelper)
    {
        $this->docCommentHelper = $docCommentHelper;
    }

    public function getLocations(Document $document, Position $position, array $nodes): \Generator
    {
        if ($document->getLanguage() !== 'php') {
            return [];
        }

        $elements = yield $this->docCommentHelper->getReflectionFromNodePath($nodes, $document, $position);

        return array_values(array_filter(array_map(function (Element $element) {
            return $element->location;
        }, $elements)));
    }
}
