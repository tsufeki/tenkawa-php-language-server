<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Index;

use Tsufeki\Tenkawa\Server\Document\Project;
use Tsufeki\Tenkawa\Server\Index\GlobalIndexer;
use Tsufeki\Tenkawa\Server\Index\Indexer;
use Tsufeki\Tenkawa\Server\Index\Storage\WritableIndexStorage;
use Tsufeki\Tenkawa\Server\Uri;
use Webmozart\PathUtil\Path;

class StubsIndexer implements GlobalIndexer
{
    /**
     * @var Uri
     */
    private $stubsUri;

    const ORIGIN = 'stubs';

    public function __construct()
    {
        foreach (['../../../../../vendor', '../../../../../../..'] as $path) {
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
            yield $indexer->indexProject($project, $globalIndexStorage, null, self::ORIGIN);
        }
    }

    public function getUriPrefixHint(): string
    {
        return (string)$this->stubsUri;
    }
}
