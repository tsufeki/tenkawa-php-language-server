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

class ImportDocCommentGlobalsCompletionProvider implements CompletionProvider
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
        $this->nodeFinder = $nodeFinder;
        $this->lexer = $lexer;
        $this->globalCompletionHelper = $globalCompletionHelper;
    }

    public function getTriggerCharacters(): array
    {
        return [];
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
        assert($nodes[1] instanceof Node);

        $nameContext = $nodes[1]->getAttribute('nameContext') ?? new NameContext();
        $name = $this->getName($nodes[0], PositionUtils::offsetFromPosition($position, $document));

        if ($name === null || strpos($name, '\\') !== false) {
            return new CompletionList();
        }

        return yield $this->globalCompletionHelper->getImportCompletions(
            $document,
            $position,
            [CompletionItemKind::CLASS_],
            $nameContext
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
