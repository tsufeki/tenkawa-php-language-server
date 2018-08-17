<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Index;

use Tsufeki\Tenkawa\Server\Document\Project;
use Tsufeki\Tenkawa\Server\Index\GlobalIndexer;
use Tsufeki\Tenkawa\Server\Index\Indexer;
use Tsufeki\Tenkawa\Server\Index\Storage\SqliteStorage;
use Tsufeki\Tenkawa\Server\Index\Storage\WritableIndexStorage;
use Tsufeki\Tenkawa\Server\Uri;
use Webmozart\PathUtil\Path;

class StubsIndexer implements GlobalIndexer
{
    /**
     * @var Uri
     */
    private $stubsUri;

    /**
     * @var string
     */
    private $indexPath;

    /**
     * @var WritableIndexStorage|null
     */
    private $index;

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

        $this->indexPath = Path::canonicalize(__DIR__ . '/../../../../../data/stubs.sqlite');
    }

    /**
     * @resolve WritableIndexStorage
     */
    public function getIndex(): \Generator
    {
        if ($this->index === null) {
            $this->index = new SqliteStorage($this->indexPath, '1', (string)$this->stubsUri);
        }

        return $this->index;
        yield;
    }

    public function buildIndex(Indexer $indexer): \Generator
    {
        if ($this->stubsUri !== null) {
            $project = new Project($this->stubsUri);
            yield $indexer->indexProject($project, yield $this->getIndex(), null, self::ORIGIN);
        }
    }
}
