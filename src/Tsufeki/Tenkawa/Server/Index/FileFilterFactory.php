<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Index;

use Tsufeki\Tenkawa\Server\Document\Project;
use Tsufeki\Tenkawa\Server\Io\FileLister\FileFilter;

interface FileFilterFactory
{
    /**
     * @resolve FileFilter
     */
    public function getFilter(Project $project): \Generator;
}
