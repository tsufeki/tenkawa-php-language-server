<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Reflection;

use League\HTMLToMarkdown\HtmlConverter;
use Tsufeki\Tenkawa\Php\Index\StubsIndexer;
use Tsufeki\Tenkawa\Php\Reflection\Element\ClassLike;
use Tsufeki\Tenkawa\Php\Reflection\Element\Const_;
use Tsufeki\Tenkawa\Php\Reflection\Element\DocComment;
use Tsufeki\Tenkawa\Php\Reflection\Element\Element;
use Tsufeki\Tenkawa\Php\Reflection\Element\Function_;

class StubsReflectionTransformer
{
    /**
     * @var HtmlConverter
     */
    private $htmlConverter;

    public function __construct(HtmlConverter $htmlConverter)
    {
        $this->htmlConverter = $htmlConverter;
    }

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
                $this->transformDocComment($method->docComment);
            }
            foreach ($element->properties as $property) {
                $this->transformDocComment($property->docComment);
            }
            foreach ($element->consts as $const) {
                $this->transformDocComment($const->docComment);
            }
        }

        if ($element instanceof Const_ && $element->name === '\\PHP_INT_MIN') {
            $element->valueExpression = (string)PHP_INT_MIN;
        }

        $this->transformDocComment($element->docComment);

        return $element;
        yield;
    }

    private function transformFunction(Function_ $function): void
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

    private function transformDocComment(?DocComment $docComment): void
    {
        if ($docComment === null || empty($docComment->text)) {
            return;
        }

        $text = substr(trim($docComment->text), 3, -2);
        $text = preg_replace('~^\s*\*\s~m', '', $text);
        $text = preg_replace('~^(\s*)@~m', '<br>$1@', $text);
        $text = str_replace("\n\n", "<br>\n\n", $text);
        $text = "/**\n" . $this->htmlConverter->convert($text) . "\n*/";
        $text = str_replace('\\[\\]', '[]', $text);
        $text = str_replace('```', "\n```", $text);
        $text = preg_replace_callback(
            '~\\$([a-zA-Z0-9_]|\\\\_)*~',
            function ($match) {
                return str_replace('\\', '', $match[0]);
            },
            $text
        );

        $docComment->text = $text;
    }
}
