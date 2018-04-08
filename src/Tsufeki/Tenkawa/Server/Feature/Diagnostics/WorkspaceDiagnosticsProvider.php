<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\Diagnostics;

use Tsufeki\Tenkawa\Server\Document\Document;

interface WorkspaceDiagnosticsProvider
{
    /**
     * @param Document[] $documents
     *
     * @resolve array<string,Diagnostic[]> URI => diagnostics
     */
    public function getWorkspaceDiagnostics(array $documents): \Generator;
}
