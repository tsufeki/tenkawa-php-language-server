<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\PhpStan;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use PHPStan\Analyser\Scope;
use PHPStan\Type\IntersectionType as PhpStanIntersectionType;
use PHPStan\Type\Type as PhpStanType;
use PHPStan\Type\TypeWithClassName;
use PHPStan\Type\UnionType as PhpStanUnionType;
use PHPStan\Type\VerbosityLevel;
use Tsufeki\Tenkawa\Php\Parser\Ast;
use Tsufeki\Tenkawa\Php\Parser\Parser;
use Tsufeki\Tenkawa\Php\PhpStan\Analyser\Analyser;
use Tsufeki\Tenkawa\Php\PhpStan\Utils\AstPruner;
use Tsufeki\Tenkawa\Php\TypeInference\BasicType;
use Tsufeki\Tenkawa\Php\TypeInference\IntersectionType;
use Tsufeki\Tenkawa\Php\TypeInference\ObjectType;
use Tsufeki\Tenkawa\Php\TypeInference\Type;
use Tsufeki\Tenkawa\Php\TypeInference\TypeInference;
use Tsufeki\Tenkawa\Php\TypeInference\UnionType;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Utils\Cache;

class PhpStanTypeInference implements TypeInference
{
    /**
     * @var Analyser
     */
    private $analyser;

    /**
     * @var Parser
     */
    private $parser;

    /**
     * @var AstPruner
     */
    private $astPruner;

    private const IGNORED_EXPR_NODES = [
        Expr\ArrayItem::class => true,
        Expr\Error::class => true,
        Scalar\EncapsedStringPart::class => true,
    ];

    public function __construct(Analyser $analyser, Parser $parser, AstPruner $astPruner)
    {
        $this->analyser = $analyser;
        $this->parser = $parser;
        $this->astPruner = $astPruner;
    }

    /**
     * @param (Node|Comment)[]|null $nodePath
     */
    public function infer(Document $document, ?array $nodePath = null, ?Cache $cache = null): \Generator
    {
        if ($document->getLanguage() !== 'php') {
            return;
        }

        /** @var Ast $ast */
        $ast = yield $this->parser->parse($document);
        $nodes = $ast->nodes;
        if ($nodePath !== null) {
            $nodes = yield $this->astPruner->pruneToCurrentFunction($nodePath, $nodes);
        }

        yield $this->analyser->analyse(
            $document,
            function (Node $node, Scope $scope) {
                if ($node instanceof Expr && !isset(self::IGNORED_EXPR_NODES[get_class($node)])) {
                    $type = $scope->getType($node);
                    $node->setAttribute('type', $this->processType($type));
                    $node->setAttribute('phpstanType', $type);
                }
            },
            $nodes,
            $cache
        );
    }

    private function processType(PhpStanType $phpStanType): Type
    {
        if ($phpStanType instanceof TypeWithClassName) {
            $type = new ObjectType();
            $type->class = '\\' . ltrim($phpStanType->getClassName(), '\\');

            return $type;
        }

        if ($phpStanType instanceof PhpStanUnionType) {
            $type = new UnionType();
            $type->types = array_map(function (PhpStanType $subtype) {
                return $this->processType($subtype);
            }, $phpStanType->getTypes());

            return $type;
        }

        if ($phpStanType instanceof PhpStanIntersectionType) {
            $type = new IntersectionType();
            $type->types = array_map(function (PhpStanType $subtype) {
                return $this->processType($subtype);
            }, $phpStanType->getTypes());

            return $type;
        }

        $type = new BasicType();
        $type->description = $phpStanType->describe(VerbosityLevel::value());

        return $type;
    }
}
