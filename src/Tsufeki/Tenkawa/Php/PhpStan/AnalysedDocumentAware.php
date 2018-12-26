<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\PhpStan;

use Tsufeki\Tenkawa\Server\Document\Document;

interface AnalysedDocumentAware
{
    public function setDocument(?Document $document): void;
}
