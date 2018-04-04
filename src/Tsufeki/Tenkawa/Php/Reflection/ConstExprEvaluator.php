<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Reflection;

use Tsufeki\Tenkawa\Php\Parser\Parser;
use Tsufeki\Tenkawa\Server\Document\Document;

class ConstExprEvaluator
{
    /**
     * @var Parser
     */
    private $parser;

    /**
     * @var ReflectionProvider
     */
    private $reflectionProvider;

    /**
     * @var ClassResolver
     */
    private $classResolver;

    public function __construct(
        Parser $parser,
        ReflectionProvider $reflectionProvider,
        ClassResolver $classResolver
    ) {
        $this->parser = $parser;
        $this->reflectionProvider = $reflectionProvider;
        $this->classResolver = $classResolver;
    }

    /**
     * @resolve mixed
     */
    public function evaluate(string $expr, NameContext $nameContext, Document $document): \Generator
    {
        return yield (new ConstExprEvaluation(
            $this->parser,
            $this->reflectionProvider,
            $this->classResolver,
            $document
        ))->evaluate($expr, $nameContext);
    }

    /**
     * @resolve mixed
     */
    public function getConstValue(Element\Const_ $const, Document $document): \Generator
    {
        return yield (new ConstExprEvaluation(
            $this->parser,
            $this->reflectionProvider,
            $this->classResolver,
            $document
        ))->getConstValue($const);
    }
}
