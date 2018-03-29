<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Reflection;

use PhpParser\Node;
use PhpParser\Node\Const_ as ConstNode;
use PhpParser\Node\Expr;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Name\Relative;
use PhpParser\Node\NullableType;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt;
use PhpParser\PrettyPrinter\Standard;
use Tsufeki\Tenkawa\Php\Reflection\Element\ClassConst;
use Tsufeki\Tenkawa\Php\Reflection\Element\ClassLike;
use Tsufeki\Tenkawa\Php\Reflection\Element\Const_;
use Tsufeki\Tenkawa\Php\Reflection\Element\DocComment;
use Tsufeki\Tenkawa\Php\Reflection\Element\Element;
use Tsufeki\Tenkawa\Php\Reflection\Element\Function_;
use Tsufeki\Tenkawa\Php\Reflection\Element\Method;
use Tsufeki\Tenkawa\Php\Reflection\Element\Param;
use Tsufeki\Tenkawa\Php\Reflection\Element\Property;
use Tsufeki\Tenkawa\Php\Reflection\Element\TraitAlias;
use Tsufeki\Tenkawa\Php\Reflection\Element\TraitInsteadOf;
use Tsufeki\Tenkawa\Php\Reflection\Element\Type;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Protocol\Common\Location;
use Tsufeki\Tenkawa\Server\Utils\PositionUtils;

class ReflectionVisitor extends NameContextVisitor
{
    /**
     * @var Document
     */
    private $document;

    /**
     * @var Standard
     */
    private $prettyPrinter;

    /**
     * @var ClassLike[]
     */
    private $classes = [];

    /**
     * @var Function_[]
     */
    private $functions = [];

    /**
     * @var Const_[]
     */
    private $consts = [];

    /**
     * @var (ClassLike|null)[]
     */
    private $classLikeStack = [];

    /**
     * @var (Function_|null)[]
     */
    private $functionStack = [];

    const VARARG_FUNCTIONS = [
        'func_get_args',
        'func_get_arg',
        'func_num_args',
    ];

    public function __construct(Document $document, Standard $prettyPrinter)
    {
        parent::__construct();
        $this->document = $document;
        $this->prettyPrinter = $prettyPrinter;
    }

    private function nameToString(Name $name): string
    {
        if ($name instanceof FullyQualified) {
            return '\\' . $name->toString();
        }
        if ($name instanceof Relative) {
            return 'namespace\\' . $name->toString();
        }

        return $name->toString();
    }

    /**
     * @param Name|NullableType|string|null $type
     *
     * @return Type|null
     */
    private function getType($type)
    {
        if (empty($type)) {
            return null;
        }

        $typeObj = new Type();

        if (is_string($type)) {
            $typeObj->type = $type;
        } elseif ($type instanceof NullableType) {
            $innerType = $this->getType($type->type);
            if ($innerType === null) {
                return null;
            }
            $typeObj->type = '?' . $innerType->type;
        } else {
            $typeObj->type = $this->nameToString($type);
        }

        return $typeObj;
    }

    /**
     * @param Stmt\ClassLike|Stmt\Function_|Stmt\ClassMethod|ConstNode|Stmt\PropertyProperty $node
     */
    private function init(Element $element, Node $node, Node $docCommentFallback = null)
    {
        $this->setName($element, $node);
        $this->setCommonInfo($element, $node, $docCommentFallback);
    }

    /**
     * @param Stmt\ClassLike|Stmt\Function_|Stmt\ClassMethod|ConstNode|Stmt\PropertyProperty $node
     */
    private function setName(Element $element, Node $node)
    {
        if (isset($node->namespacedName)) {
            $element->name = $this->nameToString(new FullyQualified($node->namespacedName));
        } else {
            $element->name = $node->name;
        }
    }

    private function setCommonInfo(Element $element, Node $node, Node $docCommentFallback = null)
    {
        $element->location = new Location();
        $element->location->uri = $this->document->getUri();
        $element->location->range = PositionUtils::rangeFromNodeAttrs($node->getAttributes(), $this->document);

        $phpDoc = $node->getDocComment();
        if ($phpDoc === null && $docCommentFallback !== null) {
            $phpDoc = $docCommentFallback->getDocComment();
        }
        if ($phpDoc !== null) {
            $element->docComment = new DocComment();
            // We don't know the actual encoding, so here we replace invalid
            // UTF-8 bytes with '?'.
            $element->docComment->text = mb_convert_encoding($phpDoc->getText(), 'UTF-8', 'UTF-8');
        }

        $element->nameContext = clone $this->nameContext;
    }

    /**
     * @param Stmt\Function_|Stmt\ClassMethod $node
     */
    private function processFunction(Function_ $function, $node)
    {
        $this->init($function, $node);
        $function->returnByRef = $node->byRef;
        $function->returnType = $this->getType($node->returnType);

        $params = [];
        $optional = true;
        foreach (array_reverse($node->params) as $paramNode) {
            $param = new Param();
            $param->name = $paramNode->name;
            $param->byRef = $paramNode->byRef;
            $param->optional = $optional = $optional && ($paramNode->default !== null || $paramNode->variadic);
            $param->variadic = $paramNode->variadic;
            $param->type = $this->getType($paramNode->type);
            $param->defaultNull = $paramNode->default instanceof Expr\ConstFetch
                && strtolower((string)$paramNode->default->name) === 'null';
            if ($paramNode->default !== null) {
                $param->defaultExpression = mb_convert_encoding($this->prettyPrinter->prettyPrintExpr($paramNode->default), 'UTF-8', 'UTF-8');
            }

            $params[] = $param;
        }

        $function->params = array_reverse($params);
        $this->functionStack[] = $function;
    }

    /**
     * @param Method|Property|ClassConst                     $member
     * @param Stmt\ClassMethod|Stmt\Property|Stmt\ClassConst $node
     */
    private function processMember($member, Node $node)
    {
        $member->accessibility =
            $node->isPrivate() ? ClassLike::M_PRIVATE : (
            $node->isProtected() ? ClassLike::M_PROTECTED :
            ClassLike::M_PUBLIC);

        $member->static = $node instanceof Stmt\ClassConst || $node->isStatic();
    }

    private function processUsedTraits(ClassLike $class, Stmt\ClassLike $node)
    {
        foreach ($node->stmts as $child) {
            if ($child instanceof Stmt\TraitUse) {
                foreach ($child->traits as $trait) {
                    $class->traits[] = $this->nameToString($trait);
                }

                foreach ($child->adaptations as $adaptation) {
                    if ($adaptation instanceof Stmt\TraitUseAdaptation\Precedence) {
                        $insteadOf = new TraitInsteadOf();
                        $insteadOf->trait = $this->nameToString($adaptation->trait);
                        $insteadOf->method = $adaptation->method;
                        foreach ($adaptation->insteadof as $insteadOfNode) {
                            $insteadOf->insteadOfs[] = $this->nameToString($insteadOfNode);
                        }
                        $class->traitInsteadOfs[] = $insteadOf;
                    } elseif ($adaptation instanceof Stmt\TraitUseAdaptation\Alias) {
                        $alias = new TraitAlias();
                        $alias->trait = $adaptation->trait ? $this->nameToString($adaptation->trait) : null;
                        $alias->method = $adaptation->method;
                        $alias->newName = $adaptation->newName;
                        $alias->newAccessibility =
                            $adaptation->newModifier === Stmt\Class_::MODIFIER_PRIVATE ? ClassLike::M_PRIVATE : (
                            $adaptation->newModifier === Stmt\Class_::MODIFIER_PROTECTED ? ClassLike::M_PROTECTED : (
                            $adaptation->newModifier === Stmt\Class_::MODIFIER_PUBLIC ? ClassLike::M_PUBLIC :
                            null));
                        $class->traitAliases[] = $alias;
                    }
                }
            }
        }
    }

    private function processClass(ClassLike $class, Stmt\Class_ $node)
    {
        $this->init($class, $node);
        $class->isClass = true;
        $class->abstract = $node->isAbstract();
        $class->final = $node->isFinal();
        $class->parentClass = $node->extends ? $this->nameToString($node->extends) : null;
        foreach ($node->implements as $implements) {
            $class->interfaces[] = $this->nameToString($implements);
        }
        $this->processUsedTraits($class, $node);
    }

    private function processInterface(ClassLike $interface, Stmt\Interface_ $node)
    {
        $this->init($interface, $node);
        $interface->isInterface = true;
        foreach ($node->extends as $extends) {
            $interface->interfaces[] = $this->nameToString($extends);
        }
    }

    private function processTrait(ClassLike $trait, Stmt\Trait_ $node)
    {
        $this->init($trait, $node);
        $trait->isTrait = true;
        $this->processUsedTraits($trait, $node);
    }

    private function processDefineConst(Const_ $const, Expr\FuncCall $defineNode)
    {
        $nameNode = $defineNode->args[0]->value;
        assert($nameNode instanceof Scalar\String_);
        $const->name = '\\' . ltrim($nameNode->value, '\\');
        $this->setCommonInfo($const, $defineNode);
        if (isset($defineNode->args[1])) {
            $const->valueExpression = mb_convert_encoding(
                $this->prettyPrinter->prettyPrintExpr($defineNode->args[1]->value),
                'UTF-8', 'UTF-8'
            );
        }
    }

    public function enterNode(Node $node)
    {
        parent::enterNode($node);

        if ($node instanceof Stmt\Function_) {
            $function = new Function_();
            $this->processFunction($function, $node);
            $this->functions[] = $function;

            return null;
        }

        if ($node instanceof Stmt\Class_) {
            if ($node->name !== null) {
                $class = $this->classLikeStack[] = new ClassLike();
                $this->processClass($class, $node);
                $this->classes[] = $class;
            } else {
                $this->classLikeStack[] = null;
            }

            return null;
        }

        if ($node instanceof Stmt\Interface_) {
            $interface = $this->classLikeStack[] = new ClassLike();
            $this->processInterface($interface, $node);
            $this->classes[] = $interface;

            return null;
        }

        if ($node instanceof Stmt\Trait_) {
            $trait = $this->classLikeStack[] = new ClassLike();
            $this->processTrait($trait, $node);
            $this->classes[] = $trait;

            return null;
        }

        if ($node instanceof Stmt\ClassConst) {
            $class = $this->classLikeStack[count($this->classLikeStack) - 1] ?? null;
            if ($class !== null) {
                foreach ($node->consts as $constNode) {
                    $const = new ClassConst();
                    $this->init($const, $constNode, $node);
                    $const->valueExpression = mb_convert_encoding($this->prettyPrinter->prettyPrintExpr($constNode->value), 'UTF-8', 'UTF-8');
                    $this->processMember($const, $node);
                    $class->consts[] = $const;
                }
            }
        }

        if ($node instanceof Stmt\Property) {
            $class = $this->classLikeStack[count($this->classLikeStack) - 1] ?? null;
            if ($class !== null) {
                foreach ($node->props as $propertyNode) {
                    $property = new Property();
                    $this->init($property, $propertyNode, $node);
                    $this->processMember($property, $node);
                    $class->properties[] = $property;
                }
            }
        }

        if ($node instanceof Stmt\ClassMethod) {
            $class = $this->classLikeStack[count($this->classLikeStack) - 1] ?? null;
            if ($class !== null) {
                $method = new Method();
                $this->processFunction($method, $node);
                $this->processMember($method, $node);
                $method->abstract = $node->isAbstract();
                $method->final = $node->isFinal();
                $class->methods[] = $method;
            }
        }

        if ($node instanceof Stmt\Const_) {
            foreach ($node->consts as $constNode) {
                $const = new Const_();
                $this->init($const, $constNode, $node);
                $const->valueExpression = mb_convert_encoding($this->prettyPrinter->prettyPrintExpr($constNode->value), 'UTF-8', 'UTF-8');
                $this->consts[] = $const;
            }

            return null;
        }

        if ($node instanceof Expr\Closure) {
            $this->functionStack[] = null;

            return null;
        }

        if ($node instanceof Expr\FuncCall
            && $node->name instanceof Name
        ) {
            if (in_array(strtolower((string)$node->name), self::VARARG_FUNCTIONS, true)) {
                $function = $this->functionStack[count($this->functionStack) - 1] ?? null;
                if ($function !== null) {
                    $function->callsFuncGetArgs = true;
                }

                return null;
            }

            if (strtolower((string)$node->name) === 'define'
                && isset($node->args[0])
                && !$node->args[0]->unpack
                && $node->args[0]->value instanceof Scalar\String_
            ) {
                $const = new Const_();
                $this->processDefineConst($const, $node);
                $this->consts[] = $const;

                return null;
            }
        }
    }

    public function leaveNode(Node $node)
    {
        parent::leaveNode($node);

        if ($node instanceof FunctionLike) {
            array_pop($this->functionStack);
        }

        if ($node instanceof Stmt\ClassLike) {
            array_pop($this->classLikeStack);
        }
    }

    /**
     * @return ClassLike[]
     */
    public function getClasses(): array
    {
        return $this->classes;
    }

    /**
     * @return Function_[]
     */
    public function getFunctions(): array
    {
        return $this->functions;
    }

    /**
     * @return Const_[]
     */
    public function getConsts(): array
    {
        return $this->consts;
    }
}
