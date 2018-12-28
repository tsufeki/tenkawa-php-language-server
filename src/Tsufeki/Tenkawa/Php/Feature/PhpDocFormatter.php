<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature;

use phpDocumentor\Reflection\DocBlock\Tag;
use phpDocumentor\Reflection\DocBlock\Tags\Param;
use phpDocumentor\Reflection\DocBlock\Tags\Return_;
use phpDocumentor\Reflection\DocBlock\Tags\Throws;
use phpDocumentor\Reflection\DocBlock\Tags\Var_;
use phpDocumentor\Reflection\DocBlockFactoryInterface;
use phpDocumentor\Reflection\Type;
use phpDocumentor\Reflection\Types\Array_;
use phpDocumentor\Reflection\Types\Collection;
use phpDocumentor\Reflection\Types\Compound;
use phpDocumentor\Reflection\Types\Context;
use phpDocumentor\Reflection\Types\Nullable;
use phpDocumentor\Reflection\Types\Object_;
use PhpParser\Comment;
use Tsufeki\Tenkawa\Php\Reflection\NameContext;
use Tsufeki\Tenkawa\Server\Utils\StringUtils;

class PhpDocFormatter
{
    /**
     * @var DocBlockFactoryInterface
     */
    private $docBlockFactory;

    public function __construct(DocBlockFactoryInterface $docBlockFactory)
    {
        $this->docBlockFactory = $docBlockFactory;
    }

    public function format(string $docComment, NameContext $nameContext): string
    {
        $context = new Context($nameContext->namespace, $nameContext->uses);

        try {
            $phpDoc = $this->docBlockFactory->create($docComment, $context);
        } catch (\Throwable $e) {
            $docComment = (new Comment($docComment))->getReformattedText();

            return "```\n$docComment\n```";
        }

        $paragraphs = [];

        if ($summary = $phpDoc->getSummary()) {
            $paragraphs[] = $summary;
        }
        if ($description = (string)$phpDoc->getDescription()) {
            $paragraphs[] = $description;
        }

        foreach ($phpDoc->getTags() as $tag) {
            $paragraphs[] = $this->formatTag($tag);
        }

        return implode("\n\n", $paragraphs);
    }

    private function formatTag(Tag $tag): string
    {
        $tagName = $tag->getName();
        $parts = ["*@$tagName*"];

        if ($tag instanceof Var_) {
            $parts[] = $this->formatType($tag->getType());
            $parts[] = $this->formatVar($tag->getVariableName());
            $parts[] = (string)$tag->getDescription();
        } elseif ($tag instanceof Param) {
            $parts[] = $this->formatType($tag->getType());
            $parts[] = $this->formatVar($tag->getVariableName(), $tag->isVariadic() ? '...' : '');
            $parts[] = (string)$tag->getDescription();
        } elseif ($tag instanceof Throws || $tag instanceof Return_) {
            $parts[] = $this->formatType($tag->getType());
            $parts[] = (string)$tag->getDescription();
        } else {
            $parts[] = (string)$tag;
        }

        $parts = array_filter($parts, function ($part) {
            return $part !== null && $part !== '';
        });

        return implode(' ', $parts);
    }

    private function formatType(?Type $type): ?string
    {
        if ($type === null) {
            return null;
        }

        return '`' . $this->formatTypeInner($type) . '`';
    }

    private function formatTypeInner(?Type $type): ?string
    {
        if ($type === null) {
            return null;
        }

        if ($type instanceof Object_) {
            $str = (string)$type;

            return $str === 'object' ? $str : StringUtils::getShortName($str);
        }

        if ($type instanceof Array_) {
            $keyType = $this->formatTypeInner($type->getKeyType());
            $valueType = $this->formatTypeInner($type->getValueType());

            if ($keyType && $keyType !== 'string|int') {
                return "array<$keyType,$valueType>";
            }

            if ($valueType === 'mixed') {
                return 'array';
            }

            if ($type->getValueType() instanceof Compound) {
                return '(' . $valueType . ')[]';
            }

            return $valueType . '[]';
        }

        if ($type instanceof Collection) {
            $keyType = $this->formatTypeInner($type->getKeyType());
            $valueType = $this->formatTypeInner($type->getValueType());
            $fqsen = StringUtils::getShortName((string)$type->getFqsen());

            if (!$keyType || $keyType === 'string|int') {
                return "$fqsen<$valueType>";
            }

            return "$fqsen<$keyType,$valueType>";
        }

        if ($type instanceof Compound) {
            return implode('|', array_map(function ($subType) {
                return $this->formatTypeInner($subType);
            }, iterator_to_array($type->getIterator())));
        }

        if ($type instanceof Nullable) {
            return '?' . $this->formatTypeInner($type->getActualType());
        }

        return (string)$type;
    }

    private function formatVar(?string $var, string $prefix = ''): ?string
    {
        return ($var === null || $var === '') ? null : "`$prefix\$$var`";
    }
}
