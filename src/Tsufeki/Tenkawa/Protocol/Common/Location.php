<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Protocol\Common;

use Tsufeki\Tenkawa\Uri;

/**
 * Represents a location inside a resource, such as a line inside a text file.
 */
class Location
{
    /**
     * @var Uri
     */
    public $uri;

    /**
     * @var Range
     */
    public $range;
}
