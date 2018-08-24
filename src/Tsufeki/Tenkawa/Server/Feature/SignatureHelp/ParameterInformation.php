<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\SignatureHelp;

use Tsufeki\Tenkawa\Server\Feature\Common\MarkupContent;

/**
 * Represents a parameter of a callable-signature. A parameter can have a label
 * and a doc-comment.
 */
class ParameterInformation
{
    /**
     * The label of this parameter. Will be shown in the UI.
     *
     * @var string
     */
    public $label;

    /**
     * The human-readable doc-comment of this parameter. Will be shown in the
     * UI but can be omitted.
     *
     * @var string|MarkupContent|null
     */
    public $documentation;
}
