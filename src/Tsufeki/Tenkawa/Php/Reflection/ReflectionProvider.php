<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Reflection;

use Tsufeki\Tenkawa\Php\Reflection\Element\ClassLike;
use Tsufeki\Tenkawa\Php\Reflection\Element\Const_;
use Tsufeki\Tenkawa\Php\Reflection\Element\Function_;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Document\Project;
use Tsufeki\Tenkawa\Server\Uri;

interface ReflectionProvider
{
    /**
     * @param Document|Project $documentOrProject
     *
     * @resolve ClassLike[]
     */
    public function getClass($documentOrProject, string $fullyQualifiedName): \Generator;

    /**
     * @param Document|Project $documentOrProject
     *
     * @resolve Function_[]
     */
    public function getFunction($documentOrProject, string $fullyQualifiedName): \Generator;

    /**
     * @param Document|Project $documentOrProject
     *
     * @resolve Const_[]
     */
    public function getConst($documentOrProject, string $fullyQualifiedName): \Generator;

    /**
     * @param Document|Project $documentOrProject
     *
     * @resolve ClassLike[]
     */
    public function getClassesFromUri($documentOrProject, Uri $uri): \Generator;

    /**
     * @param Document|Project $documentOrProject
     *
     * @resolve Function_[]
     */
    public function getFunctionsFromUri($documentOrProject, Uri $uri): \Generator;

    /**
     * @param Document|Project $documentOrProject
     *
     * @resolve Const_[]
     */
    public function getConstsFromUri($documentOrProject, Uri $uri): \Generator;

    /**
     * @param Document|Project $documentOrProject
     *
     * @resolve ClassLike[]
     */
    public function getClassesByShortName($documentOrProject, string $shortName): \Generator;

    /**
     * @param Document|Project $documentOrProject
     *
     * @resolve Function_[]
     */
    public function getFunctionsByShortName($documentOrProject, string $shortName): \Generator;

    /**
     * @param Document|Project $documentOrProject
     *
     * @resolve Const_[]
     */
    public function getConstsByShortName($documentOrProject, string $shortName): \Generator;

    /**
     * Get class-likes extending, implementing or using (for traits) given class-like.
     *
     * @param Document|Project $documentOrProject
     *
     * @resolve string[]
     */
    public function getInheritingClasses($documentOrProject, string $fullyQualifiedName): \Generator;

    /**
     * @param Document|Project $documentOrProject
     *
     * @resolve string[]
     */
    public function getAllClassNames($documentOrProject): \Generator;

    /**
     * @param Document|Project $documentOrProject
     *
     * @resolve string[]
     */
    public function getAllFunctionNames($documentOrProject): \Generator;

    /**
     * @param Document|Project $documentOrProject
     *
     * @resolve string[]
     */
    public function getAllConstNames($documentOrProject): \Generator;
}
