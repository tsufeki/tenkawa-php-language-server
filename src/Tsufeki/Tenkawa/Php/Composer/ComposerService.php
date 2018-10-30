<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Composer;

use Tsufeki\BlancheJsonRpc\Exception\JsonException;
use Tsufeki\BlancheJsonRpc\Json;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Document\DocumentStore;
use Tsufeki\Tenkawa\Server\Document\Project;
use Tsufeki\Tenkawa\Server\Event\OnFileChange;
use Tsufeki\Tenkawa\Server\Exception\IoException;
use Tsufeki\Tenkawa\Server\Exception\UriException;
use Tsufeki\Tenkawa\Server\Index\FileFilterFactory;
use Tsufeki\Tenkawa\Server\Io\FileReader;
use Tsufeki\Tenkawa\Server\Uri;
use Tsufeki\Tenkawa\Server\Utils\StringUtils;

class ComposerService implements FileFilterFactory, OnFileChange
{
    /**
     * @var FileReader
     */
    private $fileReader;

    /**
     * @var DocumentStore
     */
    private $documentStore;

    public function __construct(FileReader $fileReader, DocumentStore $documentStore)
    {
        $this->fileReader = $fileReader;
        $this->documentStore = $documentStore;
    }

    /**
     * @internal
     *
     * @param Uri[] $uris
     */
    public function onFileChange(array $uris): \Generator
    {
        /** @var Project[] $projects */
        $projects = yield $this->documentStore->getProjects();

        foreach ($projects as $project) {
            $watchedUris = $project->get('composer.uris') ?? [];
            foreach ($watchedUris as $watchedUri) {
                foreach ($uris as $uri) {
                    if ($uri->equals($watchedUri) || $uri->isParentOf($watchedUri)) {
                        $project->set('composer.uris', null);
                        $project->set('composer.json', null);
                        $project->set('composer.filter', null);
                    }
                }
            }
        }
    }

    /**
     * @resolve \stdClass|false
     */
    private function getComposerJson(Project $project): \Generator
    {
        $json = $project->get('composer.json');
        if ($json !== null) {
            return $json;
        }

        $root = $project->getRootUri();
        $uris = [
            'composer' => Uri::fromString("$root/composer.json"),
            'installed' => Uri::fromString("$root/vendor/composer/installed.json"),
            'vendor' => Uri::fromString("$root/vendor"),
        ];

        try {
            $json = Json::decode(yield $this->fileReader->read($uris['composer']));
        } catch (IoException | JsonException | UriException $e) {
            $json = false;
        }

        $project->set('composer.uris', $uris);
        $project->set('composer.json', $json);

        return $json;
    }

    public function getFilter(Project $project): \Generator
    {
        $filter = $project->get('composer.filter');
        if ($filter !== null) {
            return $filter;
        }

        $installed = null;
        if (yield $this->getComposerJson($project)) {
            try {
                $installed = Json::decode(yield $this->fileReader->read(
                    $project->get('composer.uris')['installed']
                ));
            } catch (IoException | JsonException | UriException $e) {
            }
        }

        $vendorDir = $project->get('composer.uris')['vendor'];
        $rejectGlobs = [];
        $acceptGlobs = [];
        $forceRejectGlobs = [];

        if (is_array($installed)) {
            $rejectGlobs[] = "$vendorDir/**/*";

            foreach ($installed as $package) {
                if (!is_object($package) || !($package instanceof \stdClass)) {
                    continue;
                }
                $this->processPackage($vendorDir, $package, $rejectGlobs, $acceptGlobs, $forceRejectGlobs);
            }
        }

        $filter = new ComposerFileFilter($rejectGlobs, $acceptGlobs, $forceRejectGlobs);
        $project->set('composer.filter', $filter);

        return $filter;
    }

    /**
     * @param string[] $rejectGlobs
     * @param string[] $acceptGlobs
     * @param string[] $forceRejectGlobs
     */
    private function processPackage(
        Uri $vendorDir,
        \stdClass $package,
        array &$rejectGlobs,
        array &$acceptGlobs,
        array &$forceRejectGlobs
    ): void {
        $name = $package->name ?? null;
        if (!is_string($name)) {
            return;
        }

        $packageRoot = "$vendorDir/$name";

        $autoload = $package->autoload ?? null;
        if (!is_object($autoload) || !($autoload instanceof \stdClass)) {
            return;
        }

        if (is_array($autoload->files ?? null)) {
            foreach ($autoload->files as $file) {
                if (is_string($file)) {
                    $file = trim($file, '/');
                    $acceptGlobs[] = "$packageRoot/$file";
                }
            }
        }

        if (is_array($autoload->classmap ?? null)) {
            foreach ($autoload->classmap as $path) {
                if (is_string($path)) {
                    $path = trim($path, '/');
                    $acceptGlobs[] = "$packageRoot/$path"; // if regular file
                    $acceptGlobs[] = "$packageRoot/$path/**/*"; // if directory
                }
            }
        }

        foreach (['psr-0', 'psr-4'] as $psr) {
            if (is_object($autoload->$psr ?? null)) {
                foreach (get_object_vars($autoload->$psr) as $ns => $dirs) {
                    if (!is_array($dirs)) {
                        $dirs = [$dirs];
                    }
                    foreach ($dirs as $dir) {
                        if (is_string($dir)) {
                            $dir = trim($dir, '/');
                            $dir = ($dir === '' ? '' : '/') . $dir;
                            $acceptGlobs[] = "$packageRoot$dir/**/*";
                        }
                    }
                }
            }
        }

        if (is_array($autoload->{'exclude-from-classmap'} ?? null)) {
            foreach ($autoload->{'exclude-from-classmap'} as $path) {
                if (is_string($path)) {
                    $path = trim($path, '/');
                    $forceRejectGlobs[] = "$packageRoot/$path"; // if regular file
                    $forceRejectGlobs[] = "$packageRoot/$path/**/*"; // if directory
                }
            }
        }
    }

    /**
     * @resolve string|null
     */
    public function getAutoloadClassForFile(Document $document): \Generator
    {
        if (!StringUtils::endsWith($document->getUri()->getNormalized(), '.php')) {
            return null;
        }

        /** @var Project $project */
        $project = yield $this->documentStore->getProjectForDocument($document);
        $json = yield $this->getComposerJson($project);
        if (!$json) {
            return null;
        }

        $rootUri = $project->getRootUri();
        foreach (['autoload', 'autoload-dev'] as $key) {
            foreach (['psr-4', 'psr-0'] as $psr) {
                $autoloads = $json->$key->$psr ?? null;
                if (!is_object($autoloads)) {
                    continue;
                }

                foreach (get_object_vars($autoloads) as $ns => $pathPrefix) {
                    $subpath = Uri::fromString("$rootUri/$pathPrefix")->extractSubpath($document->getUri());
                    if ($subpath) {
                        $ns = ($psr === 'psr-4' && $ns !== '') ? '\\' . trim((string)$ns, '\\') : '';
                        $class = $ns . '\\' . str_replace('/', '\\', substr_replace($subpath, '', -4));
                        if (preg_match('/^(\\\\[A-Za-z_][A-Za-z0-9_]*)+$/', $class) === 1) {
                            return $class;
                        }
                    }
                }
            }
        }

        return null;
    }
}
