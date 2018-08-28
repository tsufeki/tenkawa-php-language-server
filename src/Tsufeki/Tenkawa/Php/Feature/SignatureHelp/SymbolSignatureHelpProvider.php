<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\SignatureHelp;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use Tsufeki\Tenkawa\Php\Feature\GlobalSymbol;
use Tsufeki\Tenkawa\Php\Feature\MemberSymbol;
use Tsufeki\Tenkawa\Php\Feature\NodeFinder;
use Tsufeki\Tenkawa\Php\Feature\Symbol;
use Tsufeki\Tenkawa\Php\Feature\SymbolExtractor;
use Tsufeki\Tenkawa\Php\Parser\Ast;
use Tsufeki\Tenkawa\Php\Parser\Parser;
use Tsufeki\Tenkawa\Php\Parser\TokenIterator;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;
use Tsufeki\Tenkawa\Server\Feature\Common\Range;
use Tsufeki\Tenkawa\Server\Feature\SignatureHelp\SignatureHelp;
use Tsufeki\Tenkawa\Server\Feature\SignatureHelp\SignatureHelpProvider;
use Tsufeki\Tenkawa\Server\Utils\PositionUtils;

class SymbolSignatureHelpProvider implements SignatureHelpProvider
{
    /**
     * @var NodeFinder
     */
    private $nodeFinder;

    /**
     * @var Parser
     */
    private $parser;

    /**
     * @var SymbolExtractor
     */
    private $symbolExtractor;

    /**
     * @var SignatureFinder[]
     */
    private $signatureFinders;

    /**
     * @param SignatureFinder[] $signatureFinders
     */
    public function __construct(
        NodeFinder $nodeFinder,
        Parser $parser,
        SymbolExtractor $symbolExtractor,
        array $signatureFinders
    ) {
        $this->nodeFinder = $nodeFinder;
        $this->parser = $parser;
        $this->symbolExtractor = $symbolExtractor;
        $this->signatureFinders = $signatureFinders;
    }

    /**
     * @resolve SignatureHelp|null
     */
    public function getSignatureHelp(Document $document, Position $position): \Generator
    {
        if ($document->getLanguage() !== 'php') {
            return null;
        }

        /** @var (Node|Comment)[] $nodes */
        $nodes = yield $this->nodeFinder->getNodePath($document, $position, true);
        $callNode = null;
        foreach ($nodes as $node) {
            if ($node instanceof Expr\FuncCall ||
                $node instanceof Expr\MethodCall ||
                $node instanceof Expr\StaticCall ||
                ($node instanceof Expr\New_ && $node->class instanceof Name)
            ) {
                $callNode = $node;
                break;
            }
            if ($node instanceof Stmt) {
                break;
            }
        }
        if (!$callNode) {
            return null;
        }

        /** @var array<int,Range> $argRanges */
        $argRanges = yield $this->findArgRanges($callNode, $document);
        $argIndex = null;
        foreach ($argRanges as $i => $argRange) {
            if (PositionUtils::contains($argRange, $position)) {
                $argIndex = $i;
                break;
            }
        }
        if ($argIndex === null) {
            return null;
        }

        $nameNode = $callNode instanceof Expr\New_ ? $callNode->class : $callNode->name;
        $namePosition = PositionUtils::rangeFromNodeAttrs($nameNode->getAttributes(), $document)->start;
        /** @var Symbol $symbol */
        $symbol = yield $this->symbolExtractor->getSymbolAt($document, $namePosition);
        if (!$this->checkSymbol($symbol, $callNode)) {
            return null;
        }

        foreach ($this->signatureFinders as $signatureFinder) {
            $signatureHelp = yield $signatureFinder->findSignature($symbol, $callNode->args, $argIndex);
            if ($signatureHelp !== null) {
                return $signatureHelp;
            }
        }

        return null;
    }

    /**
     * @param Expr\FuncCall|Expr\MethodCall|Expr\StaticCall|Expr\New_ $callNode
     *
     * @resolve Range[]
     */
    private function findArgRanges(Expr $callNode, Document $document): \Generator
    {
        /** @var Ast $ast */
        $ast = yield $this->parser->parse($document);
        $argStartPositions = [];
        $separator = '(';
        $prevNode = $firstNode = $callNode instanceof Expr\New_ ? $callNode->class : $callNode->name;
        foreach ($callNode->args as $i => $argNode) {
            $tokenIter = TokenIterator::fromBetweenNodes($prevNode, $argNode, $ast->tokens);
            $tokenIter->eatUntilType($separator);
            $tokenIter->next();
            $argStartPositions[] = PositionUtils::positionFromOffset($tokenIter->getOffset(), $document);

            $separator = ',';
            $prevNode = $argNode;
        }

        $lastNode = end($callNode->args) ?: $firstNode;
        $tokenIter = TokenIterator::fromParentNodeRemainingTokens($lastNode, $callNode, $ast->tokens);
        $tokenIter->eatUntilType(',', ')');
        if ($tokenIter->isType(',')) {
            $tokenIter->next();
            $argStartPositions[] = PositionUtils::positionFromOffset($tokenIter->getOffset(), $document);
            $tokenIter->eatUntilType(')');
        }

        $endPosition = PositionUtils::positionFromOffset($tokenIter->getOffset(), $document);
        $endPositionInclusive = $endPosition;

        // Grab succeeding whitespace when node is not properly terminated.
        // E.g. space after comma
        if (!$tokenIter->isType(')')) {
            $tokenIter = new TokenIterator($ast->tokens, $tokenIter->key(), PHP_INT_MAX, $tokenIter->getOffset());
            $tokenIter->eatWhitespace();
            $endPositionInclusive = PositionUtils::positionFromOffset($tokenIter->getOffset(), $document);
        }
        $endPositionInclusive = PositionUtils::move($endPositionInclusive, 1, $document);

        $argRanges = [];
        foreach ($argStartPositions as $i => $pos) {
            $argRanges[] = new Range($pos, $argStartPositions[$i + 1] ?? $endPositionInclusive);
        }
        if ($argRanges === []) {
            $argRanges[] = new Range($endPosition, $endPositionInclusive);
        }

        return $argRanges;
    }

    private function checkSymbol(?Symbol $symbol, Node $node): bool
    {
        if ($symbol === null) {
            return false;
        }
        if ($node instanceof Expr\New_) {
            return $symbol instanceof GlobalSymbol && $symbol->kind === GlobalSymbol::CLASS_ && $symbol->isNewExpression;
        }

        return in_array($symbol->kind, [GlobalSymbol::FUNCTION_, MemberSymbol::METHOD], true);
    }

    /**
     * @var string[]
     */
    public function getTriggerCharacters(): array
    {
        return ['(', ',', ')'];
    }
}
