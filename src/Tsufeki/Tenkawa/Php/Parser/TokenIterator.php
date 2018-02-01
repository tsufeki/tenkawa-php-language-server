<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Parser;

use PhpParser\Node;

class TokenIterator
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
    private $offset = 0;

    public function __construct(array $tokens, int $index = 0, int $offset = 0)
    {
        $this->tokens = $tokens;
        $this->index = $index;
        $this->offset = $offset;
    }

    public function valid(): bool
    {
        return isset($this->tokens[$this->index]);
    }

    /**
     * @return mixed
     */
    public function get()
    {
        return $this->tokens[$this->index] ?? [0, ''];
    }

    /**
     * @return mixed
     */
    public function getType()
    {
        return is_array($this->get()) ? $this->get()[0] : $this->get();
    }

    public function getValue(): string
    {
        return is_array($this->get()) ? $this->get()[1] : $this->get();
    }

    public function isType(...$tokenTypes): bool
    {
        return in_array($this->getType(), $tokenTypes, true);
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function eat()
    {
        $this->offset += strlen($this->getValue());
        $this->index++;
    }

    public function eatWhitespace()
    {
        while (in_array($this->getType(), self::WHITESPACE_TOKENS, true)) {
            $this->eat();
        }
    }

    public static function fromNode(Node $node, array $documentTokens): self
    {
        $index = $node->getAttribute('startTokenPos');
        $lastIndex = $node->getAttribute('endTokenPos');
        $offset = $node->getAttribute('startFilePos');

        return new static(array_slice($documentTokens, $index, $lastIndex - $index + 1), 0, $offset);
    }
}
