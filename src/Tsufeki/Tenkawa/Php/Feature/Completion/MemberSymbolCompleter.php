<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\Completion;

use Tsufeki\Tenkawa\Php\Feature\MemberSymbol;
use Tsufeki\Tenkawa\Php\Feature\Symbol;
use Tsufeki\Tenkawa\Php\Feature\SymbolReflection;
use Tsufeki\Tenkawa\Php\Reflection\ClassResolver;
use Tsufeki\Tenkawa\Php\Reflection\Element\ClassConst;
use Tsufeki\Tenkawa\Php\Reflection\Element\ClassLike;
use Tsufeki\Tenkawa\Php\Reflection\Element\Element;
use Tsufeki\Tenkawa\Php\Reflection\Element\Method;
use Tsufeki\Tenkawa\Php\Reflection\Element\Property;
use Tsufeki\Tenkawa\Php\Reflection\NameContext;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;
use Tsufeki\Tenkawa\Server\Feature\Common\TextEdit;
use Tsufeki\Tenkawa\Server\Feature\Completion\CompletionItem;
use Tsufeki\Tenkawa\Server\Feature\Completion\CompletionItemKind;

class MemberSymbolCompleter implements SymbolCompleter
{
    /**
     * @var SymbolReflection
     */
    private $symbolReflection;

    /**
     * @var ClassResolver
     */
    private $classResolver;

    const COMPLETION_KINDS = [
        Property::class => CompletionItemKind::PROPERTY,
        Method::class => CompletionItemKind::METHOD,
        ClassConst::class => CompletionItemKind::VARIABLE,
    ];

    public function __construct(SymbolReflection $symbolReflection, ClassResolver $classResolver)
    {
        $this->symbolReflection = $symbolReflection;
        $this->classResolver = $classResolver;
    }

    public function getTriggerCharacters(): array
    {
        return ['>', ':'];
    }

    /**
     * @resolve CompletionItem[]
     */
    public function getCompletions(Symbol $symbol, Position $position): \Generator
    {
        if (!($symbol instanceof MemberSymbol)) {
            return [];
        }

        /** @var ClassConst[] $consts */
        $consts = yield $this->getMembers($symbol, MemberSymbol::CLASS_CONST);
        /** @var Property[] $properties */
        $properties = yield $this->getMembers($symbol, MemberSymbol::PROPERTY);
        /** @var Method[] $methods */
        $methods = yield $this->getMembers($symbol, MemberSymbol::METHOD);

        /** @var Element[][] $allElements */
        $allElements = [];
        if ($symbol->kind === MemberSymbol::CLASS_CONST) {
            $allElements[] = $consts;
            $allElements[] = $this->filterStaticMembers($methods, true);
            $allElements[] = $this->filterStaticMembers($properties, true);

            if (yield $this->isStaticCallToNonStaticAllowed($symbol)) {
                $allElements[] = $this->filterStaticMembers($methods, false);
            }

            if ($symbol->literalClassName) {
                $classConst = new ClassConst();
                $classConst->name = 'class';
                $classConst->nameContext = new NameContext();
                $classConst->nameContext->class = (string)$symbol->objectType;
                $classConst->accessibility = ClassLike::M_PUBLIC;
                $classConst->static = true;
                $allElements[] = [$classConst];
            }
        } elseif ($symbol->kind === MemberSymbol::PROPERTY && !$symbol->static) {
            $allElements[] = $this->filterStaticMembers($properties, false);
            $allElements[] = $methods;
        } elseif ($symbol->kind === MemberSymbol::PROPERTY && $symbol->static) {
            $allElements[] = $this->filterStaticMembers($properties, true);
        } elseif ($symbol->kind === MemberSymbol::METHOD && !$symbol->static) {
            $allElements[] = $methods;
        } elseif ($symbol->kind === MemberSymbol::METHOD && $symbol->static) {
            $allElements[] = $this->filterStaticMembers($methods, true);

            if (yield $this->isStaticCallToNonStaticAllowed($symbol)) {
                $allElements[] = $this->filterStaticMembers($methods, false);
            }
        }

        $elements = yield $this->filterAccessibleMembers(
            array_merge(...$allElements),
            $symbol->nameContext,
            $symbol->document
        );

        if (!yield $this->isStaticCallToNonStaticAllowed($symbol)) {
            $elements = array_values(array_filter($elements, function (Element $element) {
                return !($element instanceof Method) || !in_array(strtolower($element->name), ['__construct', '__destruct'], true);
            }));
        }

        return array_map(function (Element $element) use ($symbol) {
            return $this->makeItem($element, $symbol);
        }, $elements);
    }

    /**
     * @resolve (ClassConst|Method|Property)[]
     */
    private function getMembers(MemberSymbol $symbol, string $kind): \Generator
    {
        /** @var array<string,(ClassConst|Method|Property)[]> $elements */
        $elements = yield $this->symbolReflection->getMemberReflectionForType($symbol->objectType, $kind, $symbol->document);

        return empty($elements) ? [] : array_merge(...array_values($elements));
    }

    /**
     * @param (ClassConst|Method|Property)[] $members
     * @param NameContext                    $nameContext
     *
     * @resolve (ClassConst|Method|Property)[]
     */
    private function filterAccessibleMembers(array $members, NameContext $nameContext, Document $document): \Generator
    {
        /** @var string[] $parentClassNames */
        $parentClassNames = [];
        if ($nameContext->class !== null) {
            /** @var ResolvedClassLike $resolveClass */
            $resolvedClass = yield $this->classResolver->resolve($nameContext->class, $document);
            while ($resolvedClass !== null) {
                $parentClassNames[] = strtolower($resolvedClass->name);
                $resolvedClass = $resolvedClass->parentClass;
            }
            $parentClassNames = $parentClassNames ?: [strtolower($nameContext->class)];
        }

        return array_values(array_filter($members, function ($element) use ($parentClassNames) {
            switch ($element->accessibility) {
                case ClassLike::M_PUBLIC:
                    return true;
                case ClassLike::M_PROTECTED:
                    return in_array(strtolower($element->nameContext->class), $parentClassNames, true);
                case ClassLike::M_PRIVATE:
                    return strtolower($element->nameContext->class) === ($parentClassNames[0] ?? '');
                default:
                    return false;
            }
        }));
    }

    /**
     * @param (ClassConst|Method|Property)[] $members
     *
     * @return (ClassConst|Method|Property)[]
     */
    private function filterStaticMembers(array $members, bool $static = true): array
    {
        return array_values(array_filter($members, function ($element) use ($static) {
            return $element->static === $static;
        }));
    }

    /**
     * @resolve bool
     */
    private function isStaticCallToNonStaticAllowed(MemberSymbol $symbol): \Generator
    {
        if ($symbol->nameContext->class === null
            || !$symbol->isInObjectContext
            || !$symbol->literalClassName
        ) {
            return false;
        }

        $lowercaseName = strtolower((string)$symbol->objectType);

        /** @var ResolvedClassLike|null $class */
        $class = yield $this->classResolver->resolve($symbol->nameContext->class, $symbol->document);
        while ($class !== null) {
            if (strtolower($class->name) === $lowercaseName) {
                return true;
            }
            $class = $class->parentClass;
        }

        if (in_array($lowercaseName, ['\\self', '\\static', '\\parent'], true)) {
            return true;
        }

        return false;
    }

    private function makeItem(Element $element, MemberSymbol $symbol): CompletionItem
    {
        $item = new CompletionItem();
        $item->label = $element->name;
        $item->kind = self::COMPLETION_KINDS[get_class($element)];
        $item->detail = $element->nameContext->class;
        $item->insertText = $element->name;

        if ($element instanceof Method) {
            $item->insertText .= '(';
            if (in_array(strtolower($element->name), ['__construct', '__destruct'])) {
                $item->kind = CompletionItemKind::CONSTRUCTOR;
            }
        }

        if ($element instanceof Property) {
            $item->filterText = $item->label;
            $item->sortText = $item->label;
            $item->label = '$' . $item->label;
            if ($element->static) {
                if ($symbol->kind === MemberSymbol::CLASS_CONST) {
                    $item->insertText = '$' . $item->insertText;
                }

                $item->textEdit = new TextEdit();
                $item->textEdit->range = $symbol->range;
                $item->textEdit->newText = '$' . $element->name;
            }
        }

        return $item;
    }
}
