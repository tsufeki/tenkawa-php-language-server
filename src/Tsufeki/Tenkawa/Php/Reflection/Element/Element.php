<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Reflection\Element;

use Tsufeki\Tenkawa\Php\Reflection\NameContext;
use Tsufeki\Tenkawa\Server\Feature\Common\Location;
use Tsufeki\Tenkawa\Server\Feature\Common\LocationLink;
use Tsufeki\Tenkawa\Server\Feature\Common\Range;

abstract class Element
{
    /**
     * @var string Fully qualified name.
     */
    public $name;

    /**
     * @var Location|null
     */
    public $location;

    /**
     * @var Range|null
     */
    public $nameRange;

    /**
     * @var DocComment|null
     */
    public $docComment;

    /**
     * @var NameContext
     */
    public $nameContext;

    /**
     * @var string|null
     */
    public $origin;

    public function toLocationLink(?Range $originSelectionRange): ?LocationLink
    {
        if ($this->location === null) {
            return null;
        }

        $link = new LocationLink();
        $link->originSelectionRange = $originSelectionRange;
        $link->targetUri = $this->location->uri;
        $link->targetRange = $this->location->range;
        $link->targetSelectionRange = $this->nameRange ?? $this->location->range;

        return $link;
    }
}
