<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Reflection;

use PhpParser\Node\Stmt;
use Tsufeki\Tenkawa\Server\Uri;

class NameHelper
{
    private const MARKER = '@';
    private const ANONYMOUS_CLASS_PREFIX = '\\Anonymous' . self::MARKER;

    public static function isSpecial(string $name): bool
    {
        return strpos($name, self::MARKER) !== false;
    }

    public static function getAnonymousClassName(Uri $uri, Stmt\Class_ $node): string
    {
        $uri = $uri->withLineNumber($node->getLine());

        return self::ANONYMOUS_CLASS_PREFIX . sha1($uri->getNormalized());
    }
}
