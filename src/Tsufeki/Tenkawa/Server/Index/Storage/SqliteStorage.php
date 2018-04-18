<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Index\Storage;

use Tsufeki\BlancheJsonRpc\Json;
use Tsufeki\KayoJsonMapper\Mapper;
use Tsufeki\KayoJsonMapper\MapperBuilder;
use Tsufeki\KayoJsonMapper\NameMangler\NullNameMangler;
use Tsufeki\Tenkawa\Server\Index\IndexEntry;
use Tsufeki\Tenkawa\Server\Index\Query;
use Tsufeki\Tenkawa\Server\Mapper\PrefixStrippingUriMapper;
use Tsufeki\Tenkawa\Server\Uri;
use Tsufeki\Tenkawa\Server\Utils\Platform;
use Webmozart\PathUtil\Path;

class SqliteStorage implements WritableIndexStorage
{
    const MEMORY = ':memory:';
    const SCHEMA_VERSION = 4;

    /**
     * @var string
     */
    private $path;

    /**
     * @var string
     */
    private $indexDataVersion;

    /**
     * @var Mapper
     */
    private $mapper;

    /**
     * @var PrefixStrippingUriMapper
     */
    private $uriMapper;

    /**
     * @var \PDO|null
     */
    private $pdo;

    public function __construct(string $path, string $indexDataVersion, string $uriPrefix = '')
    {
        $this->path = $path;
        $this->indexDataVersion = $indexDataVersion;
        $this->createMapper($uriPrefix);
    }

    private function createMapper(string $uriPrefix)
    {
        $this->uriMapper = new PrefixStrippingUriMapper($uriPrefix);

        $this->mapper = MapperBuilder::create()
            ->setNameMangler(new NullNameMangler())
            ->setPrivatePropertyAccess(false)
            ->setGuessRequiredProperties(true)
            ->setDumpNullProperties(false)
            ->throwOnMissingProperty(true)
            ->throwOnUnknownProperty(false)
            ->throwOnInfiniteRecursion(true)
            ->acceptStdClassAsArray(true)
            ->setStrictNulls(true)
            ->addLoader($this->uriMapper)
            ->addDumper($this->uriMapper)
            ->getMapper();
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
            data_class text not null,
            timestamp integer default null
        )')->execute();

        $this->getPdo()->prepare('create index if not exists tenkawa_index_source_uri
            on tenkawa_index (source_uri)')->execute();

        $this->getPdo()->prepare('create index if not exists tenkawa_index_category
            on tenkawa_index (category)')->execute();

        $this->getPdo()->prepare('create index if not exists tenkawa_index_key
            on tenkawa_index (key)')->execute();
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
            $params['uri'] = $this->uriMapper->stripPrefixNormalized($query->uri->getNormalized());
        }

        $fields = ['source_uri', 'category', 'key'];
        if ($query->includeData) {
            $fields[] = 'data';
            $fields[] = 'data_class';
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
            $entry->sourceUri = Uri::fromString($this->uriMapper->restorePrefix($row['source_uri']));
            $entry->category = $row['category'];
            $entry->key = $row['key'];
            if ($query->includeData) {
                $entry->data = $this->mapper->load(
                    Json::decode($row['data']),
                    $row['data_class'] ?: 'mixed'
                );
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

            $stmt->execute(['sourceUri' => $this->uriMapper->stripPrefixNormalized($uri->getNormalized())]);

            $stmt = $this->getPdo()->prepare('
                insert
                    into tenkawa_index (source_uri, category, key, data, data_class, timestamp)
                    values (:sourceUri, :category, :key, :data, :dataClass, :timestamp)
            ');

            foreach ($entries as $entry) {
                $stmt->execute([
                    'sourceUri' => $this->uriMapper->stripPrefix((string)$uri),
                    'category' => $entry->category,
                    'key' => $entry->key,
                    'data' => Json::encode($this->mapper->dump($entry->data)),
                    'dataClass' => is_object($entry->data) ? get_class($entry->data) : 'mixed',
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
            $prefixUri = Uri::fromString($this->uriMapper->getPrefix());
            $having = [];
            if ($prefixUri->equals($filterUri) || $filterUri->isParentOf($prefixUri)) {
                $having[] = "uri not glob '*://*'";
            }
            if (!$prefixUri->equals($filterUri)) {
                $having[] = 'uri = :filterUri or uri glob :filterUriGlob';
                $params['filterUri'] = $this->uriMapper->stripPrefixNormalized($filterUri->getNormalized());
                $params['filterUriGlob'] = $this->uriMapper->stripPrefixNormalized($filterUri->getNormalizedWithSlash()) . '*';
            }
            $sql .= ' having ' . implode(' or ', $having);
        }

        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute($params);

        $result = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $result[$this->uriMapper->restorePrefixNormalized($row['uri'])] = (int)$row['timestamp'] ?: null;
        }

        return $result;
        yield;
    }
}
