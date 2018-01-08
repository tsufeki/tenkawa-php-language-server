<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Index;

use Tsufeki\Tenkawa\Document\Project;
use Tsufeki\Tenkawa\Index\Storage\WritableIndexStorage;
use Tsufeki\Tenkawa\Uri;
use Webmozart\PathUtil\Path;

class StubsIndexer implements GlobalIndexer
{
    /**
     * @var Uri
     */
    private $stubsUri;

    public function __construct()
    {
        foreach (['../../../../vendor', '../../../../../..'] as $path) {
            $fullPath = Path::canonicalize(__DIR__ . '/' . $path . '/jetbrains/phpstorm-stubs');
            if (is_dir($fullPath)) {
                $this->stubsUri = Uri::fromFilesystemPath($fullPath);
                break;
            }
        }
    }

    public function index(WritableIndexStorage $globalIndexStorage, Indexer $indexer): \Generator
    {
        if ($this->stubsUri !== null) {
            $project = new Project($this->stubsUri);
            yield $indexer->indexProject($project, $globalIndexStorage);
        }
    }
}
