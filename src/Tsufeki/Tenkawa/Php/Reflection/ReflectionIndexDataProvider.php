<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Reflection;

use PhpParser\NodeTraverser;
use PhpParser\PrettyPrinter\Standard;
use Tsufeki\Tenkawa\Php\Parser\Parser;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Index\IndexDataProvider;
use Tsufeki\Tenkawa\Server\Index\IndexEntry;

class ReflectionIndexDataProvider implements IndexDataProvider
{
    const CATEGORY_CLASS = 'reflection.class';
    const CATEGORY_FUNCTION = 'reflection.function';
    const CATEGORY_CONST = 'reflection.const';
    const CATEGORY_INHERITS = 'reflection.inherits';

    /**
     * @var Parser
     */
    private $parser;

    /**
     * @var Standard
     */
    private $prettyPrinter;

    public function __construct(Parser $parser, Standard $prettyPrinter)
    {
        $this->parser = $parser;
        $this->prettyPrinter = $prettyPrinter;
    }

    public function getVersion(): int
    {
        return 14;
    }

    /**
     * @resolve IndexEntry[]
     */
    public function getEntries(Document $document, string $origin = null): \Generator
    {
        if ($document->getLanguage() !== 'php') {
            return [];
        }

        $ast = yield $this->parser->parse($document);

        $visitor = new ReflectionVisitor($document, $this->prettyPrinter);
        $nodeTraverser = new NodeTraverser();
        $nodeTraverser->addVisitor($visitor);
        $nodeTraverser->traverse($ast->nodes);

        $classes = $visitor->getClasses();

        $entries = array_merge(
            $this->makeEntries($classes, self::CATEGORY_CLASS, $document, $origin),
            $this->makeEntries($visitor->getFunctions(), self::CATEGORY_FUNCTION, $document, $origin),
            $this->makeEntries($visitor->getConsts(), self::CATEGORY_CONST, $document, $origin),
            $this->makeInheritEntries($classes, $document)
        );

        return $entries;
    }

    /**
     * @param (Element\ClassLike|Element\Function_|Element\Const_)[] $elements
     *
     * @return IndexEntry[]
     */
    private function makeEntries(
        array $elements,
        string $category,
        Document $document,
        string $origin = null,
        bool $caseSensitive = true
    ): array {
        return array_map(function (Element\Element $elem) use ($category, $document, $caseSensitive, $origin) {
            $elem->origin = $origin;
            $entry = new IndexEntry();
            $entry->sourceUri = $document->getUri();
            $entry->category = $category;
            $entry->key = $caseSensitive ? $elem->name : strtolower($elem->name);
            $entry->data = $elem;

            return $entry;
        }, $elements);
    }

    /**
     * @param Element\ClassLike[] $classes
     *
     * @return IndexEntry[]
     */
    private function makeInheritEntries(
        array $classes,
        Document $document
    ): array {
        $entries = [];

        foreach ($classes as $class) {
            $inherits = array_filter(array_merge([$class->parentClass], $class->interfaces, $class->traits));
            foreach ($inherits as $inherit) {
                $entry = new IndexEntry();
                $entry->sourceUri = $document->getUri();
                $entry->category = self::CATEGORY_INHERITS;
                $entry->key = $inherit;
                $entry->data = $class->name;
                $entries[] = $entry;
            }
        }

        return $entries;
    }
}
