<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\References;

use Tsufeki\Tenkawa\Document\Document;
use Tsufeki\Tenkawa\Protocol\Server\TextDocument\SymbolInformation;
use Tsufeki\Tenkawa\Protocol\Server\TextDocument\SymbolKind;
use Tsufeki\Tenkawa\Reflection\Element\Element;
use Tsufeki\Tenkawa\Reflection\ReflectionProvider;
use Tsufeki\Tenkawa\Utils\StringUtils;

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
        $symbols = [];

        /** @var ClassLike[] $classes */
        $classes = yield $this->reflectionProvider->getClassesFromUri($this->document, $uri);

        foreach ($classes as $class) {
            $symbols[] = $symbol = new SymbolInformation();
            $symbol->name = StringUtils::getShortName($class->name);
            $symbol->kind = SymbolKind::CLASS_;
            $symbol->location = $class->location;
            $symbol->containerName = StringUtils::getNamespace($class->name);

            foreach (array_merge($class->methods, $class->properties, $class->consts) as $member) {
                if ($member->docComment === $docComment) {
                    return $member->nameContext;
                }
            }
        }

        /** @var Function_[] $functions */
        $functions = $this->reflectionProvider->getFunctionsFromUri($this->document, $uri);

        foreach ($functions as $function) {
            if ($function->docComment === $docComment) {
                return $function->nameContext;
            }
        }

        /** @var Const_[] $consts */
        $consts = $this->reflectionProvider->getConstsFromUri($this->document, $uri);

        foreach ($consts as $const) {
            if ($const->docComment === $docComment) {
                return $const->nameContext;
            }
        }

        return $symbols;
    }
}
