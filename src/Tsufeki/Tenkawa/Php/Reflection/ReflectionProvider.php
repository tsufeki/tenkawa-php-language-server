<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Reflection;

use Tsufeki\Tenkawa\Php\Reflection\Element\ClassLike;
use Tsufeki\Tenkawa\Php\Reflection\Element\Const_;
use Tsufeki\Tenkawa\Php\Reflection\Element\Function_;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Uri;

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

    /**
     * @resolve ClassLike[]
     */
    public function getClassesFromUri(Document $document, Uri $uri): \Generator;

    /**
     * @resolve Function_[]
     */
    public function getFunctionsFromUri(Document $document, Uri $uri): \Generator;

    /**
     * @resolve Const_[]
     */
    public function getConstsFromUri(Document $document, Uri $uri): \Generator;

    /**
     * @resolve ClassLike[]
     */
    public function getClassesByShortName(Document $document, string $shortName): \Generator;

    /**
     * @resolve Function_[]
     */
    public function getFunctionsByShortName(Document $document, string $shortName): \Generator;

    /**
     * @resolve Const_[]
     */
    public function getConstsByShortName(Document $document, string $shortName): \Generator;

    /**
     * Get class-likes extending, implementing or using (for traits) given class-like.
     *
     * @resolve string[]
     */
    public function getInheritingClasses(Document $document, string $fullyQualifiedName): \Generator;
}
