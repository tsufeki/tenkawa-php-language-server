<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\PhpStan;

use PhpParser\Comment;
use PhpParser\Node;
use PHPStan\Reflection\SignatureMap\FunctionSignature;
use PHPStan\Reflection\SignatureMap\ParameterSignature;
use PHPStan\Reflection\SignatureMap\SignatureMapProvider;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;
use PHPStan\Type\VerbosityLevel;
use Tsufeki\Tenkawa\Php\Feature\SignatureHelp\SignatureFinder;
use Tsufeki\Tenkawa\Php\PhpStan\IndexReflection\IndexBroker;
use Tsufeki\Tenkawa\Php\PhpStan\PhpDocResolver\PhpDocResolver;
use Tsufeki\Tenkawa\Php\Reflection\Element\Element;
use Tsufeki\Tenkawa\Php\Reflection\Element\Function_;
use Tsufeki\Tenkawa\Php\Reflection\Element\Method;
use Tsufeki\Tenkawa\Php\Reflection\Element\Param;
use Tsufeki\Tenkawa\Php\Reflection\Resolved\ResolvedMethod;
use Tsufeki\Tenkawa\Php\Symbol\Symbol;
use Tsufeki\Tenkawa\Php\Symbol\SymbolReflection;
use Tsufeki\Tenkawa\Php\TypeInference\TypeInference;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\SignatureHelp\ParameterInformation;
use Tsufeki\Tenkawa\Server\Feature\SignatureHelp\SignatureHelp;
use Tsufeki\Tenkawa\Server\Feature\SignatureHelp\SignatureInformation;
use Tsufeki\Tenkawa\Server\Utils\Cache;
use Tsufeki\Tenkawa\Server\Utils\StringUtils;
use Tsufeki\Tenkawa\Server\Utils\SyncAsync;

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

    /**
     * @var SyncAsync
     */
    private $syncAsync;

    /**
     * @var IndexBroker
     */
    private $broker;

    /**
     * @var PhpDocResolver
     */
    private $phpDocResolver;

    public function __construct(
        SignatureMapProvider $signatureMapProvider,
        TypeInference $typeInference,
        SymbolReflection $symbolReflection,
        SyncAsync $syncAsync,
        IndexBroker $broker,
        PhpDocResolver $phpDocResolver
    ) {
        $this->signatureMapProvider = $signatureMapProvider;
        $this->typeInference = $typeInference;
        $this->symbolReflection = $symbolReflection;
        $this->syncAsync = $syncAsync;
        $this->broker = $broker;
        $this->phpDocResolver = $phpDocResolver;
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

        /** @var FunctionSignature[] $candidates */
        $candidates = yield $this->getCandidates($element);
        if ($candidates === []) {
            return null;
        }

        $signatureHelp = new SignatureHelp();

        $shortName = $this->formatName($element);
        $signatureHelp->signatures = array_map(function (FunctionSignature $candidate) use ($shortName) {
            return $this->makeSignature($shortName, $candidate);
        }, $candidates);

        $cache = new Cache();
        yield $this->typeInference->infer($symbol->document, $nodePath, $cache);

        $types = [];
        foreach ($args as $arg) {
            if ($arg->unpack) {
                break;
            }

            $types[] = $arg->value->getAttribute('phpstanType') ?? new MixedType();
        }

        $signatureHelp->activeSignature = yield $this->match($candidates, $types, $symbol->document, $cache);
        $signatureHelp->activeParameter = max(0, min($argIndex, count($signatureHelp->signatures[$signatureHelp->activeSignature]->parameters) - 1));

        return $signatureHelp;
    }

    /**
     * @resolve FunctionSignature[]
     */
    private function getCandidates(Function_ $element): \Generator
    {
        $name = ltrim($element->name, '\\');
        $class = null;
        if ($element instanceof ResolvedMethod) {
            $class = $element->nameContext->class;
            $name = "$class::$name";
        }

        $candidates = [];
        $i = 0;
        $candidateName = $name;
        // TODO wrap in callSync
        while ($this->signatureMapProvider->hasFunctionSignature($candidateName)) {
            $candidates[] = $this->signatureMapProvider->getFunctionSignature($candidateName, $class);
            $i++;
            $candidateName = "$name'$i";
        }

        return $candidates;
        yield;
    }

    private function formatName(Function_ $element): string
    {
        $name = StringUtils::getShortName($element->name);
        if ($element instanceof Method && strtolower($element->name) === '__construct') {
            $name = 'new ' . StringUtils::getShortName($element->nameContext->class ?: '');
        }

        return $name;
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
    private function match(array $candidates, array $types, Document $document, Cache $cache): \Generator
    {
        $index = $this->syncAsync->callSync(
            function () use ($candidates, $types) {
                $bestMatched = -1;
                $bestCandidateIndex = null;
                foreach ($candidates as $i => $candidate) {
                    $matched = $this->matchArgs($candidate, $types);
                    if ($matched > $bestMatched) {
                        $bestMatched = $matched;
                        $bestCandidateIndex = $i;
                    }
                }

                return $bestCandidateIndex ?? 0;
            },
            [],
            function () use ($document, $cache) {
                $this->broker->setDocument($document);
                $this->broker->setCache($cache);
                $this->phpDocResolver->setDocument($document);
                $this->phpDocResolver->setCache($cache);
            },
            function () {
                $this->broker->setDocument(null);
                $this->broker->setCache(null);
                $this->phpDocResolver->setDocument(null);
                $this->phpDocResolver->setCache(null);
            }
        );

        $cache->close();

        return $index;
        yield;
    }

    private function matchArgs(FunctionSignature $candidate, array $types): int
    {
        $parameters = [];
        foreach ($candidate->getParameters() as $parameter) {
            if ($parameter->isVariadic()) {
                break;
            }
            $parameters[] = $parameter;
        }

        $matched = 0;
        foreach ($types as $i => $type) {
            $parameter = $parameters[$i] ?? null;

            $argMatches = $parameter !== null
                ? !$parameter->getType()->isSuperTypeOf($type)->no()
                : $candidate->isVariadic();

            if (!$argMatches) {
                break;
            }

            $matched++;
        }

        return $matched;
    }
}
