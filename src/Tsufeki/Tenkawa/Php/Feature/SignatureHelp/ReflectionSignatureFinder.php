<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\SignatureHelp;

use PhpParser\Comment;
use PhpParser\Node;
use Tsufeki\Tenkawa\Php\Feature\Hover\HoverFormatter;
use Tsufeki\Tenkawa\Php\Reflection\Element\Element;
use Tsufeki\Tenkawa\Php\Reflection\Element\Function_;
use Tsufeki\Tenkawa\Php\Reflection\Element\Method;
use Tsufeki\Tenkawa\Php\Reflection\Element\Param;
use Tsufeki\Tenkawa\Php\Symbol\Symbol;
use Tsufeki\Tenkawa\Php\Symbol\SymbolReflection;
use Tsufeki\Tenkawa\Server\Feature\SignatureHelp\ParameterInformation;
use Tsufeki\Tenkawa\Server\Feature\SignatureHelp\SignatureHelp;
use Tsufeki\Tenkawa\Server\Feature\SignatureHelp\SignatureInformation;
use Tsufeki\Tenkawa\Server\Utils\StringUtils;

class ReflectionSignatureFinder implements SignatureFinder
{
    /**
     * @var SymbolReflection
     */
    private $symbolReflection;

    /**
     * @var HoverFormatter
     */
    private $hoverFormatter;

    public function __construct(SymbolReflection $symbolReflection, HoverFormatter $hoverFormatter)
    {
        $this->symbolReflection = $symbolReflection;
        $this->hoverFormatter = $hoverFormatter;
    }

    /**
     * @param Node\Arg[]            $args
     * @param (Node|Comment)[]|null $nodePath
     *
     * @resolve SignatureHelp|null
     */
    public function findSignature(Symbol $symbol, array $args, int $argIndex, ?array $nodePath): \Generator
    {
        /** @var Element|null $element */
        $element = (yield $this->symbolReflection->getReflectionOrConstructorFromSymbol($symbol))[0] ?? null;
        if (!($element instanceof Function_)) {
            return null;
        }

        $signatureHelp = new SignatureHelp();
        $signatureHelp->activeSignature = 0;
        $signatureHelp->activeParameter = max(0, min($argIndex, count($element->params) - 1));
        $signature = new SignatureInformation();

        $signature->parameters = [];
        foreach ($element->params as $param) {
            $parameter = new ParameterInformation();
            $parameter->label = $this->formatParameter($param);
            $signature->parameters[] = $parameter;
        }

        $signature->label = $this->formatSignature($element, $signature->parameters);
        $signatureHelp->signatures[] = $signature;

        return $signatureHelp;
    }

    /**
     * @param ParameterInformation[] $parameters
     */
    private function formatSignature(Function_ $element, array $parameters): string
    {
        $name = StringUtils::getShortName($element->name);
        if ($element instanceof Method && strtolower($element->name) === '__construct') {
            $name = 'new ' . StringUtils::getShortName($element->nameContext->class ?: '');
        }

        return $name . '(' . implode(', ', array_map(function (ParameterInformation $p) {
            return $p->label;
        }, $parameters)) . ')';
    }

    private function formatParameter(Param $param): string
    {
        return $this->hoverFormatter->formatParam($param);
    }
}
