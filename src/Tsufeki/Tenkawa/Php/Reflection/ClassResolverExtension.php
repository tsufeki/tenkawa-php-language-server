<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Reflection;

use Tsufeki\Tenkawa\Php\Reflection\Resolved\ResolvedClassLike;
use Tsufeki\Tenkawa\Server\Document\Document;

interface ClassResolverExtension
{
    public function resolve(ResolvedClassLike $class, Document $document): \Generator;
}
