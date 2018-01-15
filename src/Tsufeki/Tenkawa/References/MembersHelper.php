<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\References;

use PhpParser\Node;
use Tsufeki\Tenkawa\Document\Document;

class MembersHelper
{
    const WHITESPACE_TOKENS = [
        T_WHITESPACE => true,
        T_COMMENT => true,
        T_DOC_COMMENT => true,
    ];

    /**
     * @return string|null
     */
    private function getReferencedMemberName(Node $node, Node $leftNode, $separatorToken, array $tokens)
    {
        $tokenIndex = $leftNode->getAttribute('startTokenPos') + 1;
        $lastTokenIndex = $node->getAttribute('endTokenPos');

        $tokenIndex = $this->eatWhitespace($tokens, $tokenIndex, $lastTokenIndex);
        if (!$this->isTokenType($tokens[$tokenIndex], $separatorToken)) {
            return null;
        }

        $tokenIndex = $this->eatWhitespace($tokens, $tokenIndex, $lastTokenIndex);
        if (!$this->isTokenType($tokens[$tokenIndex], T_STRING)) {
            return null;
        }

        return $tokens[$tokenIndex][1];
    }

    private function isTokenType($token, $tokenType): bool
    {
        return $token === $tokenType || (is_array($token) && $token[0] === $tokenType);
    }

    private function eatWhitespace(array $tokens, int $tokenIndex, int $lastTokenIndex): int
    {
        for (; $tokenIndex <= $lastTokenIndex; $tokenIndex++) {
            if (!is_array($tokens[$tokenIndex]) || !isset(self::WHITESPACE_TOKENS[$tokens[$tokenIndex][0]])) {
                break;
            }
        }

        return $tokenIndex;
    }

    /**
     * @resolve Element[]
     */
    public function getReflectionFromNode(Node $node, Document $document): \Generator
    {
        $elements = [];
    }
}
