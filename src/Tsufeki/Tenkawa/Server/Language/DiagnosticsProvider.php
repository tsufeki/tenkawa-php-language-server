<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Language;

use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Protocol\Common\Diagnostic;

interface DiagnosticsProvider
{
    /**
     * @resolve Diagnostic[]
     */
    public function getDiagnostics(Document $document): \Generator;
}
