<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Language;

use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Protocol\Common\Diagnostic;

interface WorkspaceDiagnosticsProvider
{
    /**
     * @param Document[] $documents
     *
     * @resolve array<string,Diagnostic[]> URI => diagnostics
     */
    public function getWorkspaceDiagnostics(array $documents): \Generator;
}
