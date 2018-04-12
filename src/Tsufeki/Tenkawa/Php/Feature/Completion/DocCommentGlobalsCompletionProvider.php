<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\Completion;

use PhpParser\Comment;
use PhpParser\Node;
use PHPStan\PhpDocParser\Lexer\Lexer;
use Tsufeki\Tenkawa\Php\Feature\NodeFinder;
use Tsufeki\Tenkawa\Php\Reflection\NameContext;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;
use Tsufeki\Tenkawa\Server\Feature\Completion\CompletionContext;
use Tsufeki\Tenkawa\Server\Feature\Completion\CompletionItemKind;
use Tsufeki\Tenkawa\Server\Feature\Completion\CompletionList;
use Tsufeki\Tenkawa\Server\Feature\Completion\CompletionProvider;
use Tsufeki\Tenkawa\Server\Utils\PositionUtils;

class DocCommentGlobalsCompletionProvider implements CompletionProvider
{
    /**
     * @var NodeFinder
     */
    private $nodeFinder;

    /**
     * @var Lexer
     */
    private $lexer;

    /**
     * @var GlobalsCompletionHelper
     */
    private $globalCompletionHelper;

    public function __construct(
        NodeFinder $nodeFinder,
        Lexer $lexer,
        GlobalsCompletionHelper $globalCompletionHelper
    ) {
        $this->lexer = $lexer;
        $this->nodeFinder = $nodeFinder;
        $this->globalCompletionHelper = $globalCompletionHelper;
    }

    public function getTriggerCharacters(): array
    {
        return ['\\'];
    }

    public function getCompletions(
        Document $document,
        Position $position,
        CompletionContext $context = null
    ): \Generator {
        if ($document->getLanguage() !== 'php') {
            return new CompletionList();
        }

        /** @var (Node|Comment)[] $nodes */
        $nodes = yield $this->nodeFinder->getNodePath($document, $position);

        if (count($nodes) < 2 || !($nodes[0] instanceof Comment)) {
            return new CompletionList();
        }

        $comment = $nodes[0];
        $node = $nodes[1];
        assert($node instanceof Node);

        $offset = PositionUtils::offsetFromPosition($position, $document);
        list($beforeParts, $afterParts, $absolute) = $this->getName($comment, $offset);

        if (empty($beforeParts) && empty($afterParts)) {
            return new CompletionList();
        }

        return yield $this->globalCompletionHelper->getCompletions(
            $document,
            $beforeParts,
            $afterParts,
            [CompletionItemKind::CLASS_],
            $absolute,
            $node->getAttribute('nameContext') ?? new NameContext()
        );
    }

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
                    $name = $value;
                }
                break;
            }

            $currentOffset += $length;
        }

        if ($name === null) {
            return [[], [], false];
        }

        $absolute = false;
        if (($name[0] ?? '') === '\\') {
            $absolute = true;
            $name = ltrim($name, '\\');
            $currentOffset++;
        }

        $parts = explode('\\', $name);
        $beforePartsCount = count($parts) - 1 - substr_count($name, '\\', $offset - $currentOffset);
        $beforeParts = array_slice($parts, 0, $beforePartsCount);
        $afterParts = array_slice($parts, $beforePartsCount);

        return [$beforeParts, $afterParts, $absolute];
    }
}
