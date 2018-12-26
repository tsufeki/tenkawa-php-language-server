<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Symfony\Container;

use PHPStan\Symfony\ServiceMap;
use PHPStan\Symfony\XmlContainerNotExistsException;
use PHPStan\Symfony\XmlServiceMapFactory;
use Psr\Log\LoggerInterface;
use Tsufeki\Tenkawa\Server\Document\DocumentStore;
use Tsufeki\Tenkawa\Server\Document\Project;
use Tsufeki\Tenkawa\Server\Event\Document\OnProjectOpen;
use Tsufeki\Tenkawa\Server\Event\OnFileChange;
use Tsufeki\Tenkawa\Server\Io\FileLister\FileFilter;
use Tsufeki\Tenkawa\Server\Io\FileLister\FileLister;
use Tsufeki\Tenkawa\Server\Io\FileLister\GlobFileFilter;
use Tsufeki\Tenkawa\Server\Uri;
use Tsufeki\Tenkawa\Server\Utils\Stopwatch;

class ServiceMapWatcher implements OnFileChange, OnProjectOpen
{
    /**
     * @var FileFilter[]
     */
    private $containerXmlFilters;

    /**
     * @var DocumentStore
     */
    private $documentStore;

    /**
     * @var FileLister
     */
    private $fileLister;

    /**
     * @var LoggerInterface
     */
    private $logger;

    private const SERVICE_MAP_KEY = 'symfony.service_map';
    private const SERVICE_MAP_URI_KEY = 'symfony.service_map.uri';

    /**
     * @param string[] $containerXmlGlobs
     */
    public function __construct(
        array $containerXmlGlobs,
        DocumentStore $documentStore,
        FileLister $fileLister,
        LoggerInterface $logger
    ) {
        $this->containerXmlFilters = array_map(function (string $glob) {
            return new GlobFileFilter($glob, 'xml');
        }, $containerXmlGlobs);

        $this->documentStore = $documentStore;
        $this->fileLister = $fileLister;
        $this->logger = $logger;
    }

    public function onProjectOpen(Project $project): \Generator
    {
        yield $this->loadServiceMap($project);
    }

    /**
     * @param Uri[] $uris
     */
    public function onFileChange(array $uris): \Generator
    {
        /** @var Project[] $projects */
        $projects = yield $this->documentStore->getProjects();

        foreach ($projects as $project) {
            $serviceMapUri = $project->get(self::SERVICE_MAP_URI_KEY);
            if ($serviceMapUri !== null) {
                foreach ($uris as $uri) {
                    if ($uri->equals($serviceMapUri) || $uri->isParentOf($serviceMapUri)) {
                        yield $this->loadServiceMap($project);
                    }
                }
            }
        }
    }

    public function getServiceMap(Project $project): ?ServiceMap
    {
        return $project->get(self::SERVICE_MAP_KEY);
    }

    private function loadServiceMap(Project $project): \Generator
    {
        $time = new Stopwatch();
        if (!function_exists('simplexml_load_file') || $project->getRootUri()->getScheme() !== 'file') {
            return;
        }

        foreach ($this->containerXmlFilters as $filter) {
            /** @var string $uriString */
            foreach (yield $this->fileLister->list($project->getRootUri(), [$filter]) as $uriString => $_) {
                try {
                    $uri = Uri::fromString($uriString);
                    $factory = new XmlServiceMapFactory($uri->getFilesystemPath());
                    $serviceMap = $factory->create();

                    $project->set(self::SERVICE_MAP_KEY, $serviceMap);
                    $project->set(self::SERVICE_MAP_URI_KEY, $uri);

                    $this->logger->debug("Symfony container loaded [$time]");

                    return;
                } catch (XmlContainerNotExistsException $e) {
                }
            }
        }

        $project->set(self::SERVICE_MAP_KEY, null);
        $project->set(self::SERVICE_MAP_URI_KEY, null);
    }
}
