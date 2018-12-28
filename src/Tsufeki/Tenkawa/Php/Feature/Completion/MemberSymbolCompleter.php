<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\Completion;

use Tsufeki\Tenkawa\Php\Feature\MemberSymbol;
use Tsufeki\Tenkawa\Php\Feature\Symbol;
use Tsufeki\Tenkawa\Php\Feature\SymbolReflection;
use Tsufeki\Tenkawa\Php\Reflection\ClassResolver;
use Tsufeki\Tenkawa\Php\Reflection\Element\ClassLike;
use Tsufeki\Tenkawa\Php\Reflection\Element\Element;
use Tsufeki\Tenkawa\Php\Reflection\Element\Method;
use Tsufeki\Tenkawa\Php\Reflection\Element\Property;
use Tsufeki\Tenkawa\Php\Reflection\NameContext;
use Tsufeki\Tenkawa\Php\Reflection\Resolved\ResolvedClassConst;
use Tsufeki\Tenkawa\Php\Reflection\Resolved\ResolvedClassLike;
use Tsufeki\Tenkawa\Php\Reflection\Resolved\ResolvedMethod;
use Tsufeki\Tenkawa\Php\Reflection\Resolved\ResolvedProperty;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;
use Tsufeki\Tenkawa\Server\Feature\Common\Range;
use Tsufeki\Tenkawa\Server\Feature\Common\TextEdit;
use Tsufeki\Tenkawa\Server\Feature\Completion\CompletionItem;
use Tsufeki\Tenkawa\Server\Feature\Completion\CompletionItemKind;
use Tsufeki\Tenkawa\Server\Feature\Completion\CompletionList;

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

    private const COMPLETION_KINDS = [
        ResolvedProperty::class => CompletionItemKind::PROPERTY,
        ResolvedMethod::class => CompletionItemKind::METHOD,
        ResolvedClassConst::class => CompletionItemKind::VARIABLE,
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
     * @resolve CompletionList
     */
    public function getCompletions(Symbol $symbol, Position $position): \Generator
    {
        $completions = new CompletionList();
        if (!($symbol instanceof MemberSymbol)) {
            return $completions;
        }

        $kind = $symbol->kind;

        /** @var ResolvedClassConst[] $consts */
        $consts = yield $this->getMembers($symbol, MemberSymbol::CLASS_CONST);
        /** @var ResolvedProperty[] $properties */
        $properties = yield $this->getMembers($symbol, MemberSymbol::PROPERTY);
        /** @var ResolvedMethod[] $methods */
        $methods = yield $this->getMembers($symbol, MemberSymbol::METHOD);

        /** @var (ResolvedClassConst|ResolvedMethod|ResolvedProperty)[][] $allElements */
        $allElements = [];
        if ($kind === MemberSymbol::CLASS_CONST) {
            $allElements[] = $consts;
            $allElements[] = $this->filterStaticMembers($methods, true);
            $allElements[] = $this->filterStaticMembers($properties, true);

            if (yield $this->isStaticCallToNonStaticAllowed($symbol)) {
                $allElements[] = $this->filterStaticMembers($methods, false);
            }

            if ($symbol->literalClassName) {
                $classConst = new ResolvedClassConst();
                $classConst->name = 'class';
                $classConst->nameContext = new NameContext();
                $classConst->nameContext->class = (string)$symbol->objectType;
                $classConst->accessibility = ClassLike::M_PUBLIC;
                $classConst->static = true;
                $allElements[] = [$classConst];
            }
        } elseif ($kind === MemberSymbol::PROPERTY && !$symbol->static) {
            $allElements[] = $this->filterStaticMembers($properties, false);
            $allElements[] = $methods;
        } elseif ($kind === MemberSymbol::PROPERTY && $symbol->static) {
            $allElements[] = $this->filterStaticMembers($properties, true);
        } elseif ($kind === MemberSymbol::METHOD && !$symbol->static) {
            $allElements[] = $methods;
        } elseif ($kind === MemberSymbol::METHOD && $symbol->static) {
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

        $completions->items = array_map(function (Element $element) use ($symbol, $kind, $position) {
            return $this->makeItem($element, $position, $symbol->range, $kind !== MemberSymbol::METHOD);
        }, $elements);

        return $completions;
    }

    /**
     * @resolve (ResolvedClassConst|ResolvedMethod|ResolvedProperty)[]
     */
    private function getMembers(MemberSymbol $symbol, string $kind): \Generator
    {
        /** @var array<string,(ResolvedClassConst|ResolvedMethod|ResolvedProperty)[]> $elements */
        $elements = yield $this->symbolReflection->getMemberReflectionForType($symbol->objectType, $kind, $symbol->document);

        return empty($elements) ? [] : array_merge(...array_values($elements));
    }

    /**
     * @param (ResolvedClassConst|ResolvedMethod|ResolvedProperty)[] $members
     * @param NameContext                                            $nameContext
     *
     * @resolve (ResolvedClassConst|ResolvedMethod|ResolvedProperty)[]
     */
    private function filterAccessibleMembers(array $members, NameContext $nameContext, Document $document): \Generator
    {
        /** @var string[] $parentClassNames */
        $parentClassNames = [];
        if ($nameContext->class !== null) {
            /** @var ResolvedClassLike $resolvedClass */
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
     * @param (ResolvedClassConst|ResolvedMethod|ResolvedProperty)[] $members
     *
     * @return (ResolvedClassConst|ResolvedMethod|ResolvedProperty)[]
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

    private function makeItem(Element $element, Position $position, Range $range, bool $addTrailingParen): CompletionItem
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
                $item->textEdit = new TextEdit();
                $item->textEdit->range = $range;

                if ($position == $range->start) {
                    $item->textEdit->newText = '$' . $element->name;
                    $item->insertText = '$' . $item->insertText;
                } else {
                    $item->textEdit->newText = $element->name;
                    $item->textEdit->range = clone $item->textEdit->range;
                    $item->textEdit->range->start = clone $item->textEdit->range->start;
                    $item->textEdit->range->start->character++;
                }
            }
        }

        return $item;
    }
}
