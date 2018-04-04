<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\PhpStan;

use PhpParser\Node;
use Tsufeki\Tenkawa\Php\Reflection\NameContext;
use Tsufeki\Tenkawa\Php\Reflection\NameContextVisitor;

class PhpDocResolverVisitor extends NameContextVisitor
{
    /**
     * @var array<string,NameContext> comment => name context
     */
    private $nameContexts = [];

    /**
     * @var NameContext|null
     */
    private $lastNameContext;

    public function enterNode(Node $node)
    {
        parent::enterNode($node);

        $phpDoc = $node->getDocComment();
        if ($phpDoc !== null) {
            if ($this->lastNameContext === null || $this->lastNameContext != $this->nameContext) {
                $this->lastNameContext = clone $this->nameContext;
            }
            $this->nameContexts[$phpDoc->getText()] = $this->lastNameContext;
        }
    }

    /**
     * @return array<string,NameContext> comment => name context
     */
    public function getNameContexts()
    {
        return $this->nameContexts;
    }
}
