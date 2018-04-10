<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Index\Storage;

use Tsufeki\BlancheJsonRpc\Json;
use Tsufeki\Tenkawa\Server\Index\IndexEntry;
use Tsufeki\Tenkawa\Server\Index\Query;
use Tsufeki\Tenkawa\Server\Uri;
use Tsufeki\Tenkawa\Server\Utils\Platform;
use Tsufeki\Tenkawa\Server\Utils\StringUtils;
use Webmozart\PathUtil\Path;

class SqliteStorage implements WritableIndexStorage
{
    const MEMORY = ':memory:';
    const SCHEMA_VERSION = 2;

    /**
     * @var string
     */
    private $path;

    /**
     * @var string
     */
    private $indexDataVersion;

    /**
     * @var string
     */
    private $uriPrefix;

    /**
     * @var int
     */
    private $uriPrefixLength;

    /**
     * @var string
     */
    private $uriPrefixNormalized;

    /**
     * @var \PDO|null
     */
    private $pdo;

    public function __construct(string $path, string $indexDataVersion, string $uriPrefix = '')
    {
        $this->path = $path;
        $this->indexDataVersion = $indexDataVersion;
        $this->uriPrefix = $uriPrefix === '' ? '' : rtrim($uriPrefix, '/') . '/';
        $this->uriPrefixLength = strlen($this->uriPrefix);
        $this->uriPrefixNormalized = rtrim(Uri::fromString($this->uriPrefix)->getNormalized(), '/') . '/';
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

    public function close()
    {
        $this->pdo = null;
    }

    private function initialize()
    {
        $version = self::SCHEMA_VERSION . ';' . $this->indexDataVersion;
        $this->getPdo()->prepare('pragma journal_mode=WAL')->execute();

        $this->getPdo()->prepare('create table if not exists tenkawa_version (
            version text not null
        )')->execute();

        $stmt = $this->getPdo()->prepare('select version from tenkawa_version');
        $stmt->execute();
        $dbVersion = $stmt->fetchColumn();
        $stmt = null;

        if ($dbVersion !== $version) {
            $this->getPdo()->prepare('drop table if exists tenkawa_index')->execute();
            $this->getPdo()->prepare('delete from tenkawa_version')->execute();
            $this->getPdo()->prepare('insert into tenkawa_version (version) values (:version)')
                ->execute(['version' => $version]);
        }

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

    private function stripPrefix(string $uri): string
    {
        if ($this->uriPrefixLength !== 0 && StringUtils::startsWith($uri, $this->uriPrefix)) {
            return substr($uri, $this->uriPrefixLength);
        }

        return $uri;
    }

    private function restorePrefix(string $uri, bool $normalized = false): string
    {
        if ($this->uriPrefixLength !== 0 && strpos($uri, '://') === false) {
            return ($normalized ? $this->uriPrefixNormalized : $this->uriPrefix) . $uri;
        }

        return $uri;
    }

    public function search(Query $query): \Generator
    {
        $conditions = [];
        $params = [];

        if ($query->key !== null) {
            if ($query->match === Query::FULL) {
                $conditions[] = 'key = :key';
            } elseif ($query->match === Query::PREFIX) {
                $conditions[] = "key glob :key||'*'";
            } elseif ($query->match === Query::SUFFIX) {
                $conditions[] = "key glob '*'||:key";
            }
            $params['key'] = $query->key;
        }

        if ($query->category !== null) {
            $conditions[] = 'category = :category';
            $params['category'] = $query->category;
        }

        if ($query->uri !== null) {
            if (Platform::isWindows()) {
                $conditions[] = 'lower(source_uri) = :uri';
            } else {
                $conditions[] = 'source_uri = :uri';
            }
            $params['uri'] = $this->stripPrefix($query->uri->getNormalized());
        }

        $fields = ['source_uri', 'category', 'key'];
        if ($query->includeData) {
            $fields[] = 'data';
        }

        $stmt = $this->getPdo()->prepare('
            select ' . implode(', ', $fields) . '
                from tenkawa_index
                where ' . implode(' and ', $conditions)
        );

        $stmt->execute($params);
        $result = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $entry = new IndexEntry();
            $entry->sourceUri = Uri::fromString($this->restorePrefix($row['source_uri']));
            $entry->category = $row['category'];
            $entry->key = $row['key'];
            if ($query->includeData) {
                $entry->data = Json::decode($row['data']);
            }
            $result[] = $entry;
        }

        return $result;
        yield;
    }

    public function replaceFile(Uri $uri, array $entries, int $timestamp = null): \Generator
    {
        $this->getPdo()->beginTransaction();

        try {
            if (Platform::isWindows()) {
                $condition = 'lower(source_uri) = :sourceUri';
            } else {
                $condition = 'source_uri = :sourceUri';
            }
            $stmt = $this->getPdo()->prepare("
                delete
                    from tenkawa_index
                    where $condition
            ");

            $stmt->execute(['sourceUri' => $this->stripPrefix($uri->getNormalized())]);

            $stmt = $this->getPdo()->prepare('
                insert
                    into tenkawa_index (source_uri, category, key, data, timestamp)
                    values (:sourceUri, :category, :key, :data, :timestamp)
            ');

            foreach ($entries as $entry) {
                $stmt->execute([
                    'sourceUri' => $this->stripPrefix((string)$uri),
                    'category' => $entry->category,
                    'key' => $entry->key,
                    'data' => Json::encode($entry->data),
                    'timestamp' => $timestamp,
                ]);
            }
        } catch (\Throwable $e) {
            $this->getPdo()->rollBack();

            throw $e;
        } finally {
            $this->getPdo()->commit();
        }

        return;
        yield;
    }

    public function getFileTimestamps(Uri $filterUri = null): \Generator
    {
        $params = [];

        if (Platform::isWindows()) {
            $uriColumn = 'lower(source_uri)';
        } else {
            $uriColumn = 'source_uri';
        }

        $sql = "
            select $uriColumn as uri, min(timestamp) as timestamp
                from tenkawa_index
                group by uri
        ";

        if ($filterUri !== null) {
            $sql .= " having uri = :filterUri or uri glob :filterUri||'/*'";
            $params['filterUri'] = $this->stripPrefix($filterUri->getNormalized());
        }

        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute($params);

        $result = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $result[$this->restorePrefix($row['uri'], true)] = (int)$row['timestamp'] ?: null;
        }

        return $result;
        yield;
    }
}
