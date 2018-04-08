<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\DocumentSymbols;

use Tsufeki\Tenkawa\Php\Reflection\Element\ClassConst;
use Tsufeki\Tenkawa\Php\Reflection\Element\ClassLike;
use Tsufeki\Tenkawa\Php\Reflection\Element\Const_;
use Tsufeki\Tenkawa\Php\Reflection\Element\Element;
use Tsufeki\Tenkawa\Php\Reflection\Element\Function_;
use Tsufeki\Tenkawa\Php\Reflection\Element\Method;
use Tsufeki\Tenkawa\Php\Reflection\Element\Property;
use Tsufeki\Tenkawa\Php\Reflection\ReflectionProvider;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\Location;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;
use Tsufeki\Tenkawa\Server\Feature\Common\Range;
use Tsufeki\Tenkawa\Server\Feature\Common\SymbolInformation;
use Tsufeki\Tenkawa\Server\Feature\Common\SymbolKind;
use Tsufeki\Tenkawa\Server\Feature\DocumentSymbols\DocumentSymbolsProvider;
use Tsufeki\Tenkawa\Server\Utils\StringUtils;

class ReflectionDocumentSymbolsProvider implements DocumentSymbolsProvider
{
    /**
     * @var ReflectionProvider
     */
    private $reflectionProvider;

    public function __construct(ReflectionProvider $reflectionProvider)
    {
        $this->reflectionProvider = $reflectionProvider;
    }

    public function getSymbols(Document $document): \Generator
    {
        if ($document->getLanguage() !== 'php') {
            return [];
        }

        $uri = $document->getUri();
        $defaultLocation = new Location();
        $defaultLocation->uri = $uri;
        $defaultLocation->range = new Range(new Position(0, 0), new Position(0, 0));
        $symbols = [];

        $classes = yield $this->reflectionProvider->getClassesFromUri($document, $uri);

        /** @var ClassLike $class */
        foreach ($this->sortElements($classes) as $class) {
            $symbols[] = $symbol = new SymbolInformation();
            $symbol->name = StringUtils::getShortName($class->name);
            $symbol->kind = $class->isInterface ? SymbolKind::INTERFACE_ : SymbolKind::CLASS_;
            $symbol->location = $class->location ?? $defaultLocation;
            $symbol->containerName = ltrim(StringUtils::getNamespace($class->name), '\\');

            /** @var ClassConst $const */
            foreach ($this->sortElements($class->consts) as $const) {
                $symbols[] = $symbol = new SymbolInformation();
                $symbol->name = $const->name;
                $symbol->kind = SymbolKind::CONSTANT;
                $symbol->location = $const->location ?? $defaultLocation;
                $symbol->containerName = ltrim($class->name, '\\');
            }

            /** @var Property $property */
            foreach ($this->sortElements($class->properties) as $property) {
                $symbols[] = $symbol = new SymbolInformation();
                $symbol->name = '$' . $property->name;
                $symbol->kind = SymbolKind::PROPERTY;
                $symbol->location = $property->location ?? $defaultLocation;
                $symbol->containerName = ltrim($class->name, '\\');
            }

            /** @var Method $method */
            foreach ($this->sortElements($class->methods) as $method) {
                $symbols[] = $symbol = new SymbolInformation();
                $symbol->name = $method->name;
                $symbol->kind = $this->isConstructor($method) ? SymbolKind::CONSTRUCTOR : SymbolKind::METHOD;
                $symbol->location = $method->location ?? $defaultLocation;
                $symbol->containerName = ltrim($class->name, '\\');
            }
        }

        $functions = yield $this->reflectionProvider->getFunctionsFromUri($document, $uri);

        /** @var Function_ $function */
        foreach ($this->sortElements($functions) as $function) {
            $symbols[] = $symbol = new SymbolInformation();
            $symbol->name = StringUtils::getShortName($function->name);
            $symbol->kind = SymbolKind::FUNCTION_;
            $symbol->location = $function->location ?? $defaultLocation;
            $symbol->containerName = ltrim(StringUtils::getNamespace($function->name), '\\');
        }

        $consts = yield $this->reflectionProvider->getConstsFromUri($document, $uri);

        /** @var Const_ $const */
        foreach ($this->sortElements($consts) as $const) {
            $symbols[] = $symbol = new SymbolInformation();
            $symbol->name = StringUtils::getShortName($const->name);
            $symbol->kind = SymbolKind::CONSTANT;
            $symbol->location = $const->location ?? $defaultLocation;
            $symbol->containerName = ltrim(StringUtils::getNamespace($const->name), '\\');
        }

        return $symbols;
    }

    private function isConstructor(Element $element): bool
    {
        return $element instanceof Method && in_array(strtolower($element->name), ['__construct', '__destruct']);
    }

    /**
     * @param Element[] $elements
     *
     * @return Element[]
     */
    private function sortElements(array $elements): array
    {
        usort($elements, function (Element $a, Element $b) {
            $cmp = $this->isConstructor($b) <=> $this->isConstructor($a);

            return $cmp ?: strnatcmp(StringUtils::getShortName($a->name), StringUtils::getShortName($b->name));
        });

        return $elements;
    }
}
