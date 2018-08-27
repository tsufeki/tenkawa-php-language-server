<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\PhpStan;

use PhpParser\Node;
use PHPStan\Reflection\SignatureMap\FunctionSignature;
use PHPStan\Reflection\SignatureMap\ParameterSignature;
use PHPStan\Reflection\SignatureMap\SignatureMapProvider;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;
use PHPStan\Type\VerbosityLevel;
use Tsufeki\Tenkawa\Php\Feature\SignatureHelp\SignatureFinder;
use Tsufeki\Tenkawa\Php\Feature\Symbol;
use Tsufeki\Tenkawa\Php\Feature\SymbolReflection;
use Tsufeki\Tenkawa\Php\Reflection\Element\Function_;
use Tsufeki\Tenkawa\Php\Reflection\Element\Param;
use Tsufeki\Tenkawa\Php\Reflection\Resolved\ResolvedMethod;
use Tsufeki\Tenkawa\Php\TypeInference\TypeInference;
use Tsufeki\Tenkawa\Server\Feature\SignatureHelp\ParameterInformation;
use Tsufeki\Tenkawa\Server\Feature\SignatureHelp\SignatureHelp;
use Tsufeki\Tenkawa\Server\Feature\SignatureHelp\SignatureInformation;
use Tsufeki\Tenkawa\Server\Utils\Cache;
use Tsufeki\Tenkawa\Server\Utils\StringUtils;

class PhpStanSignatureFinder implements SignatureFinder
{
    /**
     * @var SignatureMapProvider
     */
    private $signatureMapProvider;

    /**
     * @var TypeInference
     */
    private $typeInference;

    /**
     * @var SymbolReflection
     */
    private $symbolReflection;

    public function __construct(
        SignatureMapProvider $signatureMapProvider,
        TypeInference $typeInference,
        SymbolReflection $symbolReflection
    ) {
        $this->signatureMapProvider = $signatureMapProvider;
        $this->typeInference = $typeInference;
        $this->symbolReflection = $symbolReflection;
    }

    /**
     * @param Node\Arg[] $args
     *
     * @resolve SignatureHelp|null
     */
    public function findSignature(Symbol $symbol, array $args, int $argIndex): \Generator
    {
        /** @var FunctionSignature[] $candidates */
        $candidates = yield $this->getCandidates($symbol);
        if ($candidates === []) {
            return null;
        }

        $signatureHelp = new SignatureHelp();

        $shortName = StringUtils::getShortName($symbol->referencedNames[0]);
        $signatureHelp->signatures = array_map(function (FunctionSignature $candidate) use ($shortName) {
            return $this->makeSignature($shortName, $candidate);
        }, $candidates);

        $cache = new Cache();
        yield $this->typeInference->infer($symbol->document, $cache);

        $unpack = false;
        $types = array_map(function (Node\Arg $arg) use (&$unpack) {
            $unpack = $unpack || $arg->unpack;

            return $arg->value->getAttribute('phpstanType') ?? new MixedType();
        }, $args);

        $signatureHelp->activeSignature = yield $this->match($candidates, $types, $unpack);
        $signatureHelp->activeParameter = max(0, min($argIndex, count($signatureHelp->signatures[$signatureHelp->activeSignature]->parameters) - 1));

        return $signatureHelp;
    }

    /**
     * @resolve FunctionSignature[]
     */
    private function getCandidates(Symbol $symbol): \Generator
    {
        /** @var Function_|null $element */
        $element = (yield $this->symbolReflection->getReflectionFromSymbol($symbol))[0] ?? null;
        if ($element === null) {
            return [];
        }

        $name = ltrim($element->name, '\\');
        $class = null;
        if ($element instanceof ResolvedMethod) {
            $class = $element->nameContext->class;
            $name = "$class::$name";
        }

        $candidates = [];
        $i = 0;
        $candidateName = $name;
        while ($this->signatureMapProvider->hasFunctionSignature($candidateName)) {
            $candidates[] = $this->signatureMapProvider->getFunctionSignature($candidateName, $class);
            $i++;
            $candidateName = "$name'$i";
        }

        return $candidates;
    }

    private function makeSignature(string $name, FunctionSignature $candidate): SignatureInformation
    {
        $signature = new SignatureInformation();
        $signature->parameters = array_map(function (ParameterSignature $param) {
            $paramInfo = new ParameterInformation();
            $paramInfo->label = $this->formatParameter($param);

            return $paramInfo;
        }, $candidate->getParameters());
        $signature->label = $this->formatSignature($name, $signature->parameters);

        return $signature;
    }

    /**
     * @param ParameterInformation[] $parameters
     */
    private function formatSignature(string $name, array $parameters): string
    {
        return $name . '(' . implode(', ', array_map(function (ParameterInformation $p) {
            return $p->label;
        }, $parameters)) . ')';
    }

    private function formatParameter(ParameterSignature $param): string
    {
        $s = '';
        if (!($param->getType() instanceof MixedType)) {
            $s .= $param->getType()->describe(VerbosityLevel::typeOnly()) . ' ';
        }
        if ($param->passedByReference()->yes()) {
            $s .= '&';
        }
        if ($param->isVariadic()) {
            $s .= '...';
        }
        $s .= '$' . $param->getName();
        if ($param->isOptional() && !$param->isVariadic()) {
            $s .= ' = ...';
        }

        return $s;
    }

    /**
     * @param FunctionSignature[] $candidates
     * @param Type[]              $types
     *
     * @resolve int
     */
    private function match(array $candidates, array $types, bool $unpack): \Generator
    {
    }
}
