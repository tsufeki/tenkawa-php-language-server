<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Parser;

use PhpParser\Node;

class TokenIterator implements \Iterator
{
    const WHITESPACE_TOKENS = [
        T_WHITESPACE => true,
        T_COMMENT => true,
        T_DOC_COMMENT => true,
    ];

    /**
     * @var array
     */
    private $tokens;

    /**
     * @var int
     */
    private $index = 0;

    /**
     * @var int
     */
    private $endIndex;

    /**
     * @var int
     */
    private $offset = 0;

    public function __construct(array $tokens, int $startIndex, int $endIndex, int $startOffset = 0)
    {
        $this->tokens = $tokens;
        $this->index = $startIndex;
        $this->endIndex = min($endIndex, count($this->tokens));
        $this->offset = $startOffset;
    }

    public function valid()
    {
        return $this->index < $this->endIndex;
    }

    public function current()
    {
        if (!$this->valid()) {
            return [0, ''];
        }
        $token = $this->tokens[$this->index];
        if (!is_array($token)) {
            $token = [$token, $token];
        }

        return $token;
    }

    public function key()
    {
        return $this->index;
    }

    public function next()
    {
        if ($this->valid()) {
            $this->offset += strlen($this->current()[1]);
            $this->index++;
        }
    }

    public function rewind()
    {
    }

    /**
     * @return mixed
     */
    public function getType()
    {
        return $this->current()[0];
    }

    public function getValue(): string
    {
        return $this->current()[1];
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function isType(...$tokenTypes): bool
    {
        return in_array($this->getType(), $tokenTypes, true);
    }

    public function eatWhitespace()
    {
        while (isset(self::WHITESPACE_TOKENS[$this->getType()])) {
            $this->next();
        }
    }

    public function eatIfType(...$tokenTypes)
    {
        if ($this->isType(...$tokenTypes)) {
            $this->next();
        }
    }

    /**
     * Eat token until one of given types found. Matching token is not eaten.
     */
    public function eatUntilType(...$tokenTypes)
    {
        while (!$this->isType(0, ...$tokenTypes)) {
            $this->next();
        }
    }

    public static function fromNode(Node $node, array $documentTokens): self
    {
        $index = $node->getAttribute('startTokenPos');
        $lastIndex = $node->getAttribute('endTokenPos') + 1;
        $offset = $node->getAttribute('startFilePos');

        return new self($documentTokens, $index, $lastIndex, $offset);
    }

    public static function fromBetweenNodes(Node $node1, Node $node2, array $documentTokens): self
    {
        $index = $node1->getAttribute('endTokenPos') + 1;
        $lastIndex = $node2->getAttribute('startTokenPos');
        $offset = $node1->getAttribute('endFilePos') + 1;

        return new self($documentTokens, $index, $lastIndex, $offset);
    }

    public static function fromParentNodeRemainingTokens(Node $lastSubnode, Node $parentNode, array $documentTokens): self
    {
        $index = $lastSubnode->getAttribute('endTokenPos') + 1;
        $lastIndex = $parentNode->getAttribute('endTokenPos') + 1;
        $offset = $lastSubnode->getAttribute('endFilePos') + 1;

        return new self($documentTokens, $index, $lastIndex, $offset);
    }
}
