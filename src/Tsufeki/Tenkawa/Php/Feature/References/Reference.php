<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\References;

use Tsufeki\Tenkawa\Server\Feature\Common\Range;
use Tsufeki\Tenkawa\Server\Uri;

class Reference
{
    /**
     * First existing name is the actual target.
     *
     * @var string[]
     */
    public $referencedNames;

    /**
     * @var Uri
     */
    public $uri;

    /**
     * @var Range
     */
    public $range;
}
