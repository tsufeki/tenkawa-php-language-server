<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\Diagnostics;

use Tsufeki\Tenkawa\Server\Document\Document;

interface DiagnosticsProvider
{
    /**
     * @resolve Diagnostic[]
     */
    public function getDiagnostics(Document $document): \Generator;
}
