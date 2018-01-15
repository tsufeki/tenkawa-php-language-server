<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\References;

use PhpParser\Comment;
use PhpParser\Node;
use PHPStan\PhpDocParser\Parser\PhpDocParser;

class DocCommentHelper
{
    /**
     * @var PhpDocParser
     */
    private $phpDocParser;

    public function __construct(PhpDocParser $phpDocParser)
    {
        $this->phpDocParser = $phpDocParser;
    }

    public function getReferencedClassName(Comment $comment, Node $node, int $fileOffset)
    {
    }
}
