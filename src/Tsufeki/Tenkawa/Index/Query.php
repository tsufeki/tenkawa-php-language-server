<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Index;

use Tsufeki\Tenkawa\Uri;

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
     * @var Uri|null
     */
    public $uri = null;

    /**
     * If false, storage may skip loading data field into returned entries.
     *
     * @var bool
     */
    public $includeData = true;
}
