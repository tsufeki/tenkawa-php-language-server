<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Utils;

final class InfiniteRecursionMarker
{
    /**
     * @var self|null
     */
    private static $instance;

    private function __construct()
    {
    }

    public static function get(): self
    {
        return self::$instance ?? (self::$instance = new self());
    }
}
