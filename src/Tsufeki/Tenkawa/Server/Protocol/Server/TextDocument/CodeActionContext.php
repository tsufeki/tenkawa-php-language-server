<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Protocol\Server\TextDocument;

use Tsufeki\Tenkawa\Server\Protocol\Common\Diagnostic;

/**
 * Contains additional diagnostic information about the context in which
 * a code action is run.
 */
class CodeActionContext
{
    /**
     * An array of diagnostics.
     *
     * @var Diagnostic[]
     */
    public $diagnostics;
}
