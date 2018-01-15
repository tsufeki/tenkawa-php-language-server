<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\References;

use Tsufeki\Tenkawa\Document\Document;
use Tsufeki\Tenkawa\Protocol\Common\Position;
use Tsufeki\Tenkawa\Reflection\Element\Element;

class GoToGlobalsProvider implements GoToDefinitionProvider
{
    /**
     * @var GlobalsHelper
     */
    private $globalsHelper;

    public function __construct(GlobalsHelper $globalsHelper)
    {
        $this->globalsHelper = $globalsHelper;
    }

    public function getLocations(Document $document, Position $position, array $nodes): \Generator
    {
        if ($document->getLanguage() !== 'php') {
            return [];
        }

        $elements = yield $this->globalsHelper->getReflectionFromNodePath($nodes, $document);

        return array_filter(array_map(function (Element $element) {
            return $element->location;
        }, $elements));
    }
}
