<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Event\Document;

use Tsufeki\Tenkawa\Server\Document\Project;

interface OnProjectClose
{
    public function onProjectClose(Project $project): \Generator;
}
