<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\References;

use Tsufeki\Tenkawa\Document\Document;
use Tsufeki\Tenkawa\Protocol\Common\Position;
use Tsufeki\Tenkawa\Reflection\Element\Element;

class GoToMembersProvider implements GoToDefinitionProvider
{
    /**
     * @var MembersHelper
     */
    private $membersHelper;

    public function __construct(MembersHelper $membersHelper)
    {
        $this->membersHelper = $membersHelper;
    }

    public function getLocations(Document $document, Position $position, array $nodes): \Generator
    {
        if ($document->getLanguage() !== 'php') {
            return [];
        }

        $elements = yield $this->membersHelper->getReflectionFromNodePath($nodes, $document, $position);

        return array_values(array_filter(array_map(function (Element $element) {
            return $element->location;
        }, $elements)));
    }
}
