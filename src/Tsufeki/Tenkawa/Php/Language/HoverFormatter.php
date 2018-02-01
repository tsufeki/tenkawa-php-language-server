<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Language;

use PhpParser\Comment;
use Tsufeki\Tenkawa\Php\Reflection\Element\ClassConst;
use Tsufeki\Tenkawa\Php\Reflection\Element\ClassLike;
use Tsufeki\Tenkawa\Php\Reflection\Element\Const_;
use Tsufeki\Tenkawa\Php\Reflection\Element\Element;
use Tsufeki\Tenkawa\Php\Reflection\Element\Function_;
use Tsufeki\Tenkawa\Php\Reflection\Element\Method;
use Tsufeki\Tenkawa\Php\Reflection\Element\Param;
use Tsufeki\Tenkawa\Php\Reflection\Element\Property;
use Tsufeki\Tenkawa\Php\Reflection\Element\Type;
use Tsufeki\Tenkawa\Php\Reflection\Element\Variable;

class HoverFormatter
{
    public function format(Element $element): string
    {
        $s = "```php\n<?php\n";

        if ($element instanceof ClassLike) {
            $s .= $this->formatClass($element);
        } elseif ($element instanceof Method) {
            $s .= $this->formatMethod($element);
        } elseif ($element instanceof Property) {
            $s .= $this->formatProperty($element);
        } elseif ($element instanceof ClassConst) {
            $s .= $this->formatClassConst($element);
        } elseif ($element instanceof Function_) {
            $s .= $this->formatFunction($element);
        } elseif ($element instanceof Variable) {
            $s .= $this->formatVariable($element);
        } elseif ($element instanceof Const_) {
            $s .= $this->formatConst($element);
        }

        $s .= "\n```";

        if ($element->docComment) {
            $s .= $this->formatDocComment($element->docComment);
        }

        return $s;
    }

    private function formatDocComment(string $doc): string
    {
        $doc = (new Comment\Doc($doc))->getReformattedText();
        $doc = preg_replace('~\A/\*\*|\*/\z~', '', $doc);
        $doc = preg_replace('~^ \*( |$)~m', '', $doc);

        return "\n" . trim($doc);
    }

    /**
     * @param Property|Method|ClassConst $member
     */
    private function formatModifiers($member): string
    {
        $s = '';
        if ($member->accessibility === ClassLike::M_PUBLIC) {
            $s .= 'public ';
        } elseif ($member->accessibility === ClassLike::M_PROTECTED) {
            $s .= 'protected ';
        } elseif ($member->accessibility === ClassLike::M_PRIVATE) {
            $s .= 'private ';
        }

        if ($member->static) {
            $s .= 'static ';
        }

        return $s;
    }

    private function formatVariable(Variable $variable, string $class = null): string
    {
        return ($class ? $class . '::' : '') . '$' . $variable->name;
    }

    private function formatProperty(Property $property): string
    {
        return $this->formatModifiers($property) . $this->formatVariable($property, $property->nameContext->class);
    }

    private function formatConst(Const_ $const, string $class = null): string
    {
        return 'const ' . ($class ? $class . '::' : '') . $const->name;
    }

    private function formatClassConst(ClassConst $const): string
    {
        $s = '';
        if ($const->accessibility === ClassLike::M_PROTECTED) {
            $s .= 'protected ';
        } elseif ($const->accessibility === ClassLike::M_PRIVATE) {
            $s .= 'private ';
        }

        return $s . $this->formatConst($const, $const->nameContext->class);
    }

    private function formatFunction(Function_ $function, string $class = null): string
    {
        $s = 'function ';
        if ($function->returnByRef) {
            $s .= '&';
        }

        $s .= ($class ? $class . '::' : '') . $function->name . '(';
        $s .= implode(', ', array_map([$this, 'formatParam'], $function->params));
        $s .= ')';
        if ($function->returnType !== null) {
            $s .= ': ' . $this->formatType($function->returnType);
        }

        return $s;
    }

    private function formatParam(Param $param): string
    {
        $s = '';
        if ($param->type !== null) {
            $s .= $this->formatType($param->type) . ' ';
        }
        if ($param->byRef) {
            $s .= '&';
        }
        if ($param->variadic) {
            $s .= '...';
        }
        $s .= '$' . $param->name;
        if ($param->defaultNull) {
            $s .= ' = null';
        } elseif ($param->optional) {
            $s .= ' = ...';
        }

        return $s;
    }

    private function formatType(Type $type): string
    {
        return $type->type;
    }

    private function formatMethod(Method $method): string
    {
        $s = '';
        if ($method->abstract) {
            $s .= 'abstract ';
        }
        if ($method->final) {
            $s .= 'final ';
        }

        $s .= $this->formatModifiers($method);
        $s .= $this->formatFunction($method, $method->nameContext->class);

        return $s;
    }

    private function formatClass(ClassLike $class): string
    {
        $s = '';
        if ($class->abstract) {
            $s .= 'abstract ';
        }
        if ($class->final) {
            $s .= 'final ';
        }

        if ($class->isClass) {
            $s .= 'class ' . $class->name;
            if ($class->parentClass !== null) {
                $s .= ' extends ' . $class->parentClass;
            }
            if (!empty($class->intefaces)) {
                $s .= ' implements ' . implode(', ', $class->interfaces);
            }
        } elseif ($class->isInterface) {
            $s .= 'interface ' . $class->name;
            if (!empty($class->intefaces)) {
                $s .= ' extends ' . implode(', ', $class->interfaces);
            }
        } elseif ($class->isTrait) {
            $s .= 'trait ' . $class->name;
        }

        return $s;
    }
}
