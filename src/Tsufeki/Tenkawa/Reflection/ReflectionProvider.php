<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Reflection;

use Tsufeki\Tenkawa\Document\Document;
use Tsufeki\Tenkawa\Reflection\Element\ClassLike;
use Tsufeki\Tenkawa\Reflection\Element\Const_;
use Tsufeki\Tenkawa\Reflection\Element\Function_;

interface ReflectionProvider
{
    /**
     * @resolve ClassLike[]
     */
    public function getClass(Document $document, string $fullyQualifiedName): \Generator;

    /**
     * @resolve Function_[]
     */
    public function getFunction(Document $document, string $fullyQualifiedName): \Generator;

    /**
     * @resolve Const_[]
     */
    public function getConst(Document $document, string $fullyQualifiedName): \Generator;
}
