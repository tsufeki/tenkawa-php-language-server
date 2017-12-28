<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Event\Document;

use Tsufeki\Tenkawa\Document\Project;

interface OnProjectClose
{
    public function onProjectClose(Project $project): \Generator;
}
