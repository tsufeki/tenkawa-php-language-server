<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Index;

use Tsufeki\Tenkawa\Server\Uri;

class Query
{
    const PREFIX = 1;
    const SUFFIX = 2;
    const FULL = 3;

    /**
     * @var string|null
     */
    public $category = null;

    /**
     * @var string|null
     */
    public $key = null;

    /**
     * @var int
     */
    public $match = self::FULL;

    /**
     * Fuzzy filter part of the key, split by $fuzzyLastPartSeparator.
     *
     * @var string|null
     */
    public $fuzzy = null;

    /**
     * @var string|null
     */
    public $fuzzySeparator = null;

    /**
     * @var Uri|null
     */
    public $uri = null;

    /**
     * If false, storage may skip loading data field into returned entries.
     *
     * @var bool
     */
    public $includeData = true;

    /**
     * @var (string|null)[]|null
     */
    public $tag = null;

    /**
     * @var int|null
     */
    public $limit = null;
}
