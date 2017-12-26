<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Diagnostics;

use Tsufeki\Tenkawa\Document\Document;
use Tsufeki\Tenkawa\Protocol\Common\Diagnostic;

interface DiagnosticsProvider
{
    /**
     * @resolve Diagnostic[]
     */
    public function getDiagnostics(Document $document): \Generator;
}
