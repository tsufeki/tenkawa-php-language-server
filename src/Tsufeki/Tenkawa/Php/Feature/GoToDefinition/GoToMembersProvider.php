<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\GoToDefinition;

use PhpParser\Comment;
use PhpParser\Node;
use Tsufeki\Tenkawa\Php\Feature\MemberFetch;
use Tsufeki\Tenkawa\Php\Feature\MembersHelper;
use Tsufeki\Tenkawa\Php\Feature\NodeFinder;
use Tsufeki\Tenkawa\Php\Reflection\Element\Element;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;
use Tsufeki\Tenkawa\Server\Feature\GoToDefinition\GoToDefinitionProvider;

class GoToMembersProvider implements GoToDefinitionProvider
{
    /**
     * @var MembersHelper
     */
    private $membersHelper;

    /**
     * @var NodeFinder
     */
    private $nodeFinder;

    public function __construct(MembersHelper $membersHelper, NodeFinder $nodeFinder)
    {
        $this->membersHelper = $membersHelper;
        $this->nodeFinder = $nodeFinder;
    }

    public function getLocations(Document $document, Position $position): \Generator
    {
        if ($document->getLanguage() !== 'php') {
            return [];
        }

        /** @var (Node|Comment)[] $nodes */
        $nodes = yield $this->nodeFinder->getNodePath($document, $position);
        /** @var MemberFetch|null $memberFetch */
        $memberFetch = yield $this->membersHelper->getMemberFetch($nodes, $document, $position);
        if ($memberFetch === null) {
            return [];
        }

        /** @var Element[] $elements */
        $elements = yield $this->membersHelper->getReflectionFromMemberFetch($memberFetch, $document);

        return array_values(array_filter(array_map(function (Element $element) {
            return $element->location;
        }, $elements)));
    }
}
