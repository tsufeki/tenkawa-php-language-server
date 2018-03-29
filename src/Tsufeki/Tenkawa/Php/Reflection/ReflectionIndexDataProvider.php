<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Reflection;

use PhpParser\NodeTraverser;
use PhpParser\PrettyPrinter\Standard;
use Tsufeki\KayoJsonMapper\Mapper;
use Tsufeki\Tenkawa\Php\Parser\Parser;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Index\IndexDataProvider;
use Tsufeki\Tenkawa\Server\Index\IndexEntry;

class ReflectionIndexDataProvider implements IndexDataProvider
{
    const CATEGORY_CLASS = 'reflection.class';
    const CATEGORY_FUNCTION = 'reflection.function';
    const CATEGORY_CONST = 'reflection.const';

    /**
     * @var Parser
     */
    private $parser;

    /**
     * @var Mapper
     */
    private $mapper;

    /**
     * @var Standard
     */
    private $prettyPrinter;

    public function __construct(Parser $parser, Mapper $mapper, Standard $prettyPrinter)
    {
        $this->parser = $parser;
        $this->mapper = $mapper;
        $this->prettyPrinter = $prettyPrinter;
    }

    public function getVersion(): int
    {
        return 11;
    }

    /**
     * @resolve IndexEntry[]
     */
    public function getEntries(Document $document): \Generator
    {
        if ($document->getLanguage() !== 'php') {
            return [];
        }

        $ast = yield $this->parser->parse($document);

        $visitor = new ReflectionVisitor($document, $this->prettyPrinter);
        $nodeTraverser = new NodeTraverser();
        $nodeTraverser->addVisitor($visitor);
        $nodeTraverser->traverse($ast->nodes);

        $entries = array_merge(
            $this->makeEntries($visitor->getClasses(), self::CATEGORY_CLASS, $document, true),
            $this->makeEntries($visitor->getFunctions(), self::CATEGORY_FUNCTION, $document, true),
            $this->makeEntries($visitor->getConsts(), self::CATEGORY_CONST, $document, true)
        );

        return $entries;
    }

    /**
     * @param (Element\ClassLike|Element\Function_|Element\Const_)[] $elements
     *
     * @return IndexEntry[]
     */
    private function makeEntries(array $elements, string $category, Document $document, bool $caseSensitive = false): array
    {
        return array_map(function ($elem) use ($category, $document, $caseSensitive) {
            $entry = new IndexEntry();
            $entry->sourceUri = $document->getUri();
            $entry->category = $category;
            $entry->key = $caseSensitive ? $elem->name : strtolower($elem->name);
            $entry->data = $this->mapper->dump($elem);

            return $entry;
        }, $elements);
    }
}
