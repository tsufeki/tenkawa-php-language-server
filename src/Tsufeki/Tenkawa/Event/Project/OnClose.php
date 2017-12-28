<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Event\Project;

use Tsufeki\Tenkawa\Document\Project;

interface OnClose
{
    public function onClose(Project $project): \Generator;
}
