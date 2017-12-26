<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Protocol\Common;

use Tsufeki\Tenkawa\Uri;

/**
 * Text documents are identified using a URI.
 *
 * On the protocol level, URIs are passed as strings. The corresponding JSON
 * structure looks like this:
 */
class TextDocumentIdentifier
{
    /**
     * The text document's URI.
     *
     * @var Uri
     */
    public $uri;
}
