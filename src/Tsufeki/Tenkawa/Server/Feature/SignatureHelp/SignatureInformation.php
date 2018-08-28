<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\SignatureHelp;

use Tsufeki\Tenkawa\Server\Feature\Common\MarkupContent;

/**
 * Represents the signature of something callable. A signature can have a
 * label, like a function-name, a doc-comment, and a set of parameters.
 */
class SignatureInformation
{
    /**
     * The label of this signature. Will be shown in the UI.
     *
     * @var string
     */
    public $label;

    /**
     * The human-readable doc-comment of this signature. Will be shown in the
     * UI but can be omitted.
     *
     * @var string|MarkupContent|null
     */
    public $documentation;

    /**
     * The parameters of this signature.
     *
     * @var ParameterInformation[]|null
     */
    public $parameters;
}
