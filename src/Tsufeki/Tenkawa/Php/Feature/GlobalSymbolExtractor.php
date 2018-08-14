<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt;
use Tsufeki\Tenkawa\Php\Reflection\ClassResolver;
use Tsufeki\Tenkawa\Php\Reflection\NameContext;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;
use Tsufeki\Tenkawa\Server\Feature\Common\Range;
use Tsufeki\Tenkawa\Server\Utils\PositionUtils;

class GlobalSymbolExtractor implements NodePathSymbolExtractor
{
    private const NODE_KINDS = [
        Expr\ClassConstFetch::class => GlobalSymbol::CLASS_,
        Expr\Closure::class => GlobalSymbol::CLASS_,
        Expr\Instanceof_::class => GlobalSymbol::CLASS_,
        Expr\New_::class => GlobalSymbol::CLASS_,
        Expr\StaticCall::class => GlobalSymbol::CLASS_,
        Expr\StaticPropertyFetch::class => GlobalSymbol::CLASS_,
        Stmt\Catch_::class => GlobalSymbol::CLASS_,
        Stmt\ClassMethod::class => GlobalSymbol::CLASS_,
        Stmt\Class_::class => GlobalSymbol::CLASS_,
        Stmt\Function_::class => GlobalSymbol::CLASS_,
        Stmt\Interface_::class => GlobalSymbol::CLASS_,
        Stmt\TraitUse::class => GlobalSymbol::CLASS_,
        Stmt\TraitUseAdaptation\Alias::class => GlobalSymbol::CLASS_,
        Stmt\TraitUseAdaptation\Precedence::class => GlobalSymbol::CLASS_,
        NullableType::class => GlobalSymbol::CLASS_,
        Param::class => GlobalSymbol::CLASS_,
        Expr\FuncCall::class => GlobalSymbol::FUNCTION_,
        Expr\ConstFetch::class => GlobalSymbol::CONST_,
        Stmt\Namespace_::class => GlobalSymbol::NAMESPACE_,
    ];

    private const USE_KINDS = [
        Stmt\Use_::TYPE_NORMAL => GlobalSymbol::CLASS_,
        Stmt\Use_::TYPE_FUNCTION => GlobalSymbol::FUNCTION_,
        Stmt\Use_::TYPE_CONSTANT => GlobalSymbol::CONST_,
    ];

    /**
     * @var ClassResolver
     */
    private $classResolver;

    public function __construct(ClassResolver $classResolver)
    {
        $this->classResolver = $classResolver;
    }

    /**
     * @param Node|Comment $node
     */
    public function filterNode($node): bool
    {
        return $node instanceof Name;
    }

    /**
     * @param (Node|Comment)[] $nodes
     *
     * @resolve Symbol|null
     */
    public function getSymbolAt(Document $document, Position $position, array $nodes): \Generator
    {
        /** @var GlobalSymbol|null $symbol */
        $symbol = yield $this->getSymbolFromNodes($nodes, $document);

        return $symbol;
    }

    /**
     * @param (Node|Comment)[][] $nodes
     *
     * @resolve Symbol[]
     */
    public function getSymbolsInRange(Document $document, Range $range, array $nodes, ?string $symbolClass = null): \Generator
    {
        if ($symbolClass !== null && $symbolClass !== GlobalSymbol::class) {
            return [];
        }

        return array_values(array_filter(yield array_map(function (array $nodes) use ($document) {
            return $this->getSymbolFromNodes($nodes, $document);
        }, $nodes)));
    }

    /**
     * @param (Node|Comment)[] $nodes
     *
     * @resolve GlobalSymbol|null
     */
    private function getSymbolFromNodes(array $nodes, Document $document): \Generator
    {
        if (count($nodes) < 2 || !($nodes[0] instanceof Name)) {
            return null;
        }

        $name = $nodes[0];
        $node = $nodes[1];
        $parentNode = $nodes[2] ?? null;

        $symbol = new GlobalSymbol();
        $symbol->referencedNames = ['\\' . (string)$name];
        $symbol->document = $document;
        $symbol->range = PositionUtils::rangeFromNodeAttrs($name->getAttributes(), $document);
        $symbol->nameContext = $name->getAttribute('nameContext') ?? new NameContext();
        $symbol->originalName = PositionUtils::extractRange($symbol->range, $document);

        $kind = self::NODE_KINDS[get_class($node)] ?? null;
        if ($kind !== null) {
            $symbol->kind = $kind;

            if ($kind === GlobalSymbol::CLASS_) {
                $symbol->referencedNames = [yield $this->resolveClass(
                    $symbol->referencedNames[0],
                    $symbol->nameContext,
                    $document
                )];
            }

            // Unqualified function or const name can't be resolved on document
            // level. Store both alternatives.
            if ($name->getAttribute('namespacedName') instanceof Name) {
                $namespacedName = '\\' . (string)$name->getAttribute('namespacedName');
                array_unshift($symbol->referencedNames, $namespacedName);
            }
        } elseif ($node instanceof Stmt\UseUse && $parentNode instanceof Stmt\Use_) {
            $symbol->kind = self::USE_KINDS[$parentNode->type] ?? GlobalSymbol::CLASS_;
            $symbol->isImport = true;
        } elseif ($node instanceof Stmt\UseUse && $parentNode instanceof Stmt\GroupUse) {
            $symbol->kind = self::USE_KINDS[$parentNode->type] ?? self::USE_KINDS[$node->type] ?? GlobalSymbol::CLASS_;
            $symbol->isImport = true;
            $prefix = (string)$parentNode->prefix;
            if ($prefix) {
                $symbol->referencedNames[0] = '\\' . $prefix . $symbol->referencedNames[0];
            }
        } elseif ($node instanceof Stmt\GroupUse) {
            $symbol->kind = GlobalSymbol::NAMESPACE_;
            $symbol->isImport = true;
        } else {
            return null;
        }

        return $symbol;
        yield;
    }

    /**
     * @resolve string
     */
    private function resolveClass(string $className, NameContext $nameContext, Document $document): \Generator
    {
        $lowercaseName = strtolower($className);
        if ($lowercaseName === '\\self' || $lowercaseName === '\\static') {
            $className = $nameContext->class ?? $className;
        } elseif ($lowercaseName === '\\parent') {
            if ($nameContext->class !== null) {
                $className = (yield $this->classResolver->getParent($nameContext->class, $document)) ?? $className;
            }
        }

        return $className;
    }
}
