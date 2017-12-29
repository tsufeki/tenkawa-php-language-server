<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Index\Storage;

use Tsufeki\BlancheJsonRpc\Json;
use Tsufeki\Tenkawa\Index\IndexEntry;
use Tsufeki\Tenkawa\Uri;
use Webmozart\PathUtil\Path;

class SqliteStorage implements WritableIndexStorage
{
    const MEMORY = ':memory:';

    /**
     * @var string
     */
    private $path;

    /**
     * @var \PDO|null
     */
    private $pdo;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    private function getPdo(): \PDO
    {
        if ($this->pdo === null) {
            if ($this->path !== self::MEMORY) {
                @mkdir(Path::getDirectory($this->path), 0777, true);
            }
            $this->pdo = new \PDO('sqlite:' . $this->path);
            $this->initialize();
        }

        return $this->pdo;
    }

    // @codeCoverageIgnoreStart
    public function close()
    {
        $this->pdo = null;
    }

    // @codeCoverageIgnoreEnd

    private function initialize()
    {
        $this->getPdo()->prepare('create table if not exists tenkawa_index (
            id integer primary key,
            source_uri text not null,
            category text not null,
            key text not null,
            data text not null,
            timestamp integer default null
        )')->execute();

        $this->getPdo()->prepare('create index if not exists tenkawa_index_source_uri
            on tenkawa_index (source_uri)')->execute();

        $this->getPdo()->prepare('create index if not exists tenkawa_index_category
            on tenkawa_index (category)')->execute();

        $this->getPdo()->prepare('create index if not exists tenkawa_index_key
            on tenkawa_index (key)')->execute();
    }

    public function search(string $category = null, string $key, int $match = self::FULL): \Generator
    {
        $conditions = [];
        $params = ['key' => $key];

        if ($category !== null) {
            $conditions[] = 'category = :category';
            $params['category'] = $category;
        }

        if ($match === self::FULL) {
            $conditions[] = 'key = :key';
        } elseif ($match === self::PREFIX) {
            $conditions[] = "key glob :key||'*'";
        } elseif ($match === self::SUFFIX) {
            $conditions[] = "key glob '*'||:key";
        }

        $stmt = $this->getPdo()->prepare('
            select source_uri, category, key, data
                from tenkawa_index
                where ' . implode(' and ', $conditions)
        );

        $stmt->execute($params);
        $result = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $entry = new IndexEntry();
            $entry->sourceUri = Uri::fromString($row['source_uri']);
            $entry->category = $row['category'];
            $entry->key = $row['key'];
            $entry->data = Json::decode($row['data']);
            $result[] = $entry;
        }

        return $result;
        yield;
    }

    public function replaceFile(Uri $uri, array $entries, int $timestamp = null): \Generator
    {
        $this->getPdo()->beginTransaction();

        $uriString = (string)$uri;

        $stmt = $this->getPdo()->prepare('
            delete
                from tenkawa_index
                where source_uri = :sourceUri
        ');

        $stmt->execute(['sourceUri' => $uriString]);

        $stmt = $this->getPdo()->prepare('
            insert
                into tenkawa_index (source_uri, category, key, data, timestamp)
                values (:sourceUri, :category, :key, :data, :timestamp)
        ');

        foreach ($entries as $entry) {
            $stmt->execute([
                'sourceUri' => $uriString,
                'category' => $entry->category,
                'key' => $entry->key,
                'data' => Json::encode($entry->data),
                'timestamp' => $timestamp,
            ]);
        }

        $this->getPdo()->commit();

        return;
        yield;
    }

    public function getFileTimestamps(): \Generator
    {
        $stmt = $this->getPdo()->prepare('
            select source_uri, min(timestamp) as timestamp
                from tenkawa_index
                group by source_uri
        ');

        $stmt->execute();
        $result = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $result[$row['source_uri']] = (int)$row['timestamp'] ?: null;
        }

        return $result;
        yield;
    }
}
