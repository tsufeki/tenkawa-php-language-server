<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Language;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Name;
use Tsufeki\Tenkawa\Php\Reflection\Element\Element;
use Tsufeki\Tenkawa\Php\Reflection\ReflectionProvider;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Language\CodeActionProvider;
use Tsufeki\Tenkawa\Server\Protocol\Common\Command;
use Tsufeki\Tenkawa\Server\Protocol\Common\Position;
use Tsufeki\Tenkawa\Server\Protocol\Common\Range;
use Tsufeki\Tenkawa\Server\Protocol\Server\TextDocument\CodeActionContext;
use Tsufeki\Tenkawa\Server\Utils\PositionUtils;

class ImportGlobalCodeActionProvider implements CodeActionProvider
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
     * @var ReflectionProvider
     */
    private $reflectionProvider;

    public function __construct(GlobalsHelper $globalsHelper, NodeFinder $nodeFinder, ReflectionProvider $reflectionProvider)
    {
        $this->globalsHelper = $globalsHelper;
        $this->nodeFinder = $nodeFinder;
        $this->reflectionProvider = $reflectionProvider;
    }

    /**
     * @resolve Command[]
     */
    public function getCodeActions(Document $document, Range $range, CodeActionContext $context): \Generator
    {
        if ($document->getLanguage() !== 'php') {
            return [];
        }

        /** @var (Node|Comment)[] $nodes */
        $nodes = yield $this->nodeFinder->getNodesIntersectingWithRange($document, $range);

        $version = $document->getVersion();
        $commands = [];
        foreach ($nodes as $node) {
            if ($node instanceof Name) {
                $nodeRange = PositionUtils::rangeFromNodeAttrs($node->getAttributes(), $document);
                /** @var Command $command */
                foreach (yield $this->getCodeActionsAtPosition($nodeRange->start, $document, $version) as $command) {
                    $commands[$command->arguments[2] . '-' . $command->arguments[3]] = $command;
                }
            }
        }

        return array_values($commands);
    }

    private function getCodeActionsAtPosition(Position $position, Document $document, int $version = null): \Generator
    {
        /** @var (Node|Comment)[] $nodes */
        $nodes = yield $this->nodeFinder->getNodePath($document, $position);
        if (count($nodes) < 2 || !($nodes[0] instanceof Name)) {
            return [];
        }

        $name = $nodes[0]->getAttribute('originalName', $nodes[0]);
        $parentNode = $nodes[1];
        if (!($name instanceof Name) ||
            $name instanceof Name\FullyQualified ||
            $name instanceof Name\Relative
        ) {
            return [];
        }

        /** @var Element[] $existingElements */
        $existingElements = yield $this->globalsHelper->getReflectionFromNodePath($nodes, $document);
        if (!empty($existingElements)) {
            return [];
        }

        $elements = [];
        $kind = '';
        if (isset(GlobalsHelper::CLASS_REFERENCING_NODES[get_class($parentNode)])) {
            $elements = yield $this->reflectionProvider->getClassesByShortName($document, (string)$name);
        } elseif (isset(GlobalsHelper::FUNCTION_REFERENCING_NODES[get_class($parentNode)])) {
            $elements = yield $this->reflectionProvider->getFunctionsByShortName($document, (string)$name);
            $kind = 'function';
        } elseif (isset(GlobalsHelper::CONST_REFERENCING_NODES[get_class($parentNode)])) {
            $elements = yield $this->reflectionProvider->getConstsByShortName($document, (string)$name);
            $kind = 'const';
        } else {
            return [];
        }

        $commands = [];
        /** @var Element $element */
        foreach ($elements as $element) {
            $fullName = ltrim($element->name, '\\');
            $command = new Command();
            $command->title = "Import $fullName";
            $command->command = ImportCommandProvider::COMMAND;
            $command->arguments = [
                $document->getUri()->getNormalized(),
                $position,
                $kind,
                '\\' . $fullName,
                $version,
            ];
            $commands[] = $command;
        }

        return $commands;
    }
}
