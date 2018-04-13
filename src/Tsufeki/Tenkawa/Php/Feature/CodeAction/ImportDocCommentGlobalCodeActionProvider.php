<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\CodeAction;

use PhpParser\Comment;
use PhpParser\Node;
use PHPStan\PhpDocParser\Lexer\Lexer;
use Tsufeki\Tenkawa\Php\Feature\ImportHelper;
use Tsufeki\Tenkawa\Php\Feature\NodeFinder;
use Tsufeki\Tenkawa\Php\Reflection\NameContext;
use Tsufeki\Tenkawa\Php\Reflection\ReflectionProvider;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\CodeAction\CodeActionContext;
use Tsufeki\Tenkawa\Server\Feature\CodeAction\CodeActionProvider;
use Tsufeki\Tenkawa\Server\Feature\Common\Command;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;
use Tsufeki\Tenkawa\Server\Feature\Common\Range;
use Tsufeki\Tenkawa\Server\Utils\PositionUtils;

class ImportDocCommentGlobalCodeActionProvider implements CodeActionProvider
{
    /**
     * @var ImportHelper
     */
    private $importHelper;

    /**
     * @var NodeFinder
     */
    private $nodeFinder;

    /**
     * @var Lexer
     */
    private $lexer;

    /**
     * @var ReflectionProvider
     */
    private $reflectionProvider;

    public function __construct(
        ImportHelper $importHelper,
        NodeFinder $nodeFinder,
        Lexer $lexer,
        ReflectionProvider $reflectionProvider
    ) {
        $this->importHelper = $importHelper;
        $this->nodeFinder = $nodeFinder;
        $this->lexer = $lexer;
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
            if ($node instanceof Comment) {
                /** @var Command $command */
                foreach (yield $this->getCodeActionsAtPosition($node, $range->start, $document, $version) as $command) {
                    $commands[$command->arguments[2] . '-' . $command->arguments[3]] = $command;
                }
            }
        }

        return array_values($commands);
    }

    /**
     * @resolve Command[]
     */
    private function getCodeActionsAtPosition(
        Comment $comment,
        Position $position,
        Document $document,
        int $version = null
    ): \Generator {
        $name = $this->getName($comment, PositionUtils::offsetFromPosition($position, $document));
        if ($name === null || ($name[0] ?? '') === '\\') {
            return [];
        }

        /** @var (Node|Comment)[] $nodes */
        $nodes = yield $this->nodeFinder->getNodePath(
            $document,
            PositionUtils::positionFromOffset($comment->getFilePos(), $document)
        );

        $nameContext = null;
        if (count($nodes) >= 2 && $nodes[1] instanceof Node) {
            $nameContext = $nodes[1]->getAttribute('nameContext');
        }
        $nameContext = $nameContext ?? new NameContext();

        return yield $this->importHelper->getCodeActions(
            $name,
            '',
            $nameContext,
            $position,
            $document,
            $version
        );
    }

    /**
     * @return string|null
     */
    private function getName(Comment $comment, int $fileOffset)
    {
        $tokens = $this->lexer->tokenize($comment->getText());
        $offset = $fileOffset - $comment->getFilePos();
        $currentOffset = 0;
        $name = null;
        foreach ($tokens as list($value, $type)) {
            $length = strlen($value);
            if ($currentOffset <= $offset && $offset <= $currentOffset + $length) {
                if ($type === Lexer::TOKEN_IDENTIFIER) {
                    return $value;
                }
                break;
            }

            $currentOffset += $length;
        }

        return null;
    }
}
