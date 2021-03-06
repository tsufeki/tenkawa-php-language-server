<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\PhpStan\Analyser;

use Tsufeki\Tenkawa\Server\Document\Project;

interface AnalysedProjectAware
{
    public function setProject(?Project $project): void;
}
