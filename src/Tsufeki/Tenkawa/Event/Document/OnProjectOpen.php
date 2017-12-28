<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Event\Document;

use Tsufeki\Tenkawa\Document\Project;

interface OnProjectOpen
{
    public function onProjectOpen(Project $project): \Generator;
}
