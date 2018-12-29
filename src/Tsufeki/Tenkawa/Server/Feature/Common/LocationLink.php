<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\Common;

use Tsufeki\Tenkawa\Server\Uri;

/**
 * Represents a link between a source and a target location.
 */
class LocationLink
{
    /**
     * Span of the origin of this link.
     *
     * Used as the underlined span for mouse interaction. Defaults to the word
     * range at the mouse position.
     *
     * @var Range|null
     */
    public $originSelectionRange;

    /**
     * The target resource identifier of this link.
     *
     * @var Uri
     */
    public $targetUri;

    /**
     * The full target range of this link.
     *
     * @var Range
     */
    public $targetRange;

    /**
     * The span of this link.
     *
     * @var Range|null
     */
    public $targetSelectionRange;

    public function toLocation(): Location
    {
        $location = new Location();
        $location->uri = $this->targetUri;
        $location->range = $this->targetRange;

        return $location;
    }
}
