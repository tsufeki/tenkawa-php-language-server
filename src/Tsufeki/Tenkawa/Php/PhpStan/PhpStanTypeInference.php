<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\PhpStan;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use PHPStan\Analyser\Scope;
use PHPStan\Type\IntersectionType as PhpStanIntersectionType;
use PHPStan\Type\Type as PhpStanType;
use PHPStan\Type\TypeWithClassName;
use PHPStan\Type\UnionType as PhpStanUnionType;
use PHPStan\Type\VerbosityLevel;
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

    private const IGNORED_EXPR_NODES = [
        Expr\Error::class => true,
        Scalar\EncapsedStringPart::class => true,
    ];

    public function __construct(Analyser $analyser)
    {
        $this->analyser = $analyser;
    }

    public function infer(Document $document, ?Cache $cache = null): \Generator
    {
        if ($document->getLanguage() !== 'php') {
            return;
        }

        if ($cache !== null) {
            $key = 'phpstan_type_inference.' . $document->getUri()->getNormalized();
            if ($cache->get($key)) {
                return;
            }
            $cache->set($key, true);
        }

        yield $this->analyser->analyse(
            $document,
            function (Node $node, Scope $scope) {
                if ($node instanceof Expr && !isset(self::IGNORED_EXPR_NODES[get_class($node)])) {
                    $type = $scope->getType($node);
                    $node->setAttribute('type', $this->processType($type));
                }
            }
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
