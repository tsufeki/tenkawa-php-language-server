<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Reflection;

use Tsufeki\Tenkawa\Php\Index\StubsIndexer;
use Tsufeki\Tenkawa\Php\Reflection\Element\ClassLike;
use Tsufeki\Tenkawa\Php\Reflection\Element\Element;
use Tsufeki\Tenkawa\Php\Reflection\Element\Function_;

class StubsReflectionTransformer
{
    /**
     * @resolve Element
     */
    public function transform(Element $element): \Generator
    {
        if ($element->origin !== StubsIndexer::ORIGIN) {
            return $element;
        }

        if ($element instanceof Function_) {
            $this->transformFunction($element);
        }

        if ($element instanceof ClassLike) {
            foreach ($element->methods as $method) {
                $this->transformFunction($method);
            }
        }

        return $element;
        yield;
    }

    private function transformFunction(Function_ $function)
    {
        if (!$function->docComment) {
            return;
        }

        $optionalParams = [];

        $function->docComment->text = preg_replace_callback(
            '~@param' .
            '([ \t]+([^$]\S*))?' .
            '([ \t]+\$([a-zA-Z0-9_]+))?' .
            '([ \t]+(\[optional\]))?~',
            function ($matches) use (&$optionalParams) {
                if ($matches[4] ?? '') {
                    if ($matches[6] ?? '') {
                        $optionalParams[$matches[4]] = true;
                    }

                    $matches[1] = str_replace('callback', 'callable', $matches[1] ?? '');
                }

                return '@param' . ($matches[1] ?? '') . ($matches[3] ?? '') . ($matches[5] ?? '');
            },
            $function->docComment->text
        );

        foreach ($function->params as $param) {
            if (isset($optionalParams[$param->name])) {
                $param->optional = true;
            }
        }
    }
}
