<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Document;

use Tsufeki\Tenkawa\Server\Event\Document\OnChange;
use Tsufeki\Tenkawa\Server\Event\Document\OnClose;
use Tsufeki\Tenkawa\Server\Event\Document\OnOpen;
use Tsufeki\Tenkawa\Server\Event\Document\OnProjectClose;
use Tsufeki\Tenkawa\Server\Event\Document\OnProjectOpen;
use Tsufeki\Tenkawa\Server\Event\EventDispatcher;
use Tsufeki\Tenkawa\Server\Exception\DocumentNotOpenException;
use Tsufeki\Tenkawa\Server\Exception\ProjectNotOpenException;
use Tsufeki\Tenkawa\Server\Uri;

class DocumentStore
{
    /**
     * @var Document[]
     */
    private $documents = [];

    /**
     * @var Project[]
     */
    private $projects = [];

    /**
     * @var EventDispatcher
     */
    private $eventDispatcher;

    public function __construct(EventDispatcher $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @throws DocumentNotOpenException
     */
    public function get(Uri $uri): Document
    {
        $uriString = (string)$uri;
        if (!isset($this->documents[$uriString])) { // TODO: windows support
            throw new DocumentNotOpenException();
        }

        return $this->documents[$uriString];
    }

    /**
     * @resolve Document
     */
    public function open(Uri $uri, string $language, string $text, int $version = null): \Generator
    {
        $project = yield $this->getProjectForUri($uri);
        $document = new Document($uri, $language, $project);
        $document->update($text, $version);
        $project->addDocument($document);
        $uriString = (string)$document->getUri();
        $this->documents[$uriString] = $document;

        yield $this->eventDispatcher->dispatch(OnOpen::class, $document);

        return $document;
    }

    /**
     * Get document but don't track it as open.
     *
     * @resolve Document
     */
    public function load(Uri $uri, string $language, string $text, Project $project = null): \Generator
    {
        $project = $project ?? yield $this->getProjectForUri($uri);
        $document = new Document($uri, $language, $project);
        $document->update($text);

        return $document;
    }

    public function update(Document $document, string $text, int $version = null): \Generator
    {
        // Check if open
        $this->get($document->getUri());

        $document->update($text, $version);

        yield $this->eventDispatcher->dispatch(OnChange::class, $document);
    }

    public function close(Document $document): \Generator
    {
        // Check if open
        $this->get($document->getUri());

        $uriString = (string)$document->getUri();
        unset($this->documents[$uriString]);
        $document->getProject()->removeDocument($document);
        $document->close();

        yield $this->eventDispatcher->dispatch(OnClose::class, $document);
    }

    /**
     * @throws ProjectNotOpenException
     */
    public function getProject(Uri $rootUri): Project
    {
        $uriString = (string)$rootUri;
        if (!isset($this->projects[$uriString])) { // TODO: windows support
            throw new ProjectNotOpenException();
        }

        return $this->projects[$uriString];
    }

    /**
     * @resolve Project
     *
     * @throws ProjectNotOpenException
     */
    private function getProjectForUri(Uri $uri): \Generator
    {
        if (empty($this->projects)) {
            throw new ProjectNotOpenException();
        }

        // TODO: multi-root workspace
        return array_values($this->projects)[0];
        yield;
    }

    public function openProject(Uri $rootUri): \Generator
    {
        $project = new Project($rootUri);
        $uriString = (string)$project->getRootUri();
        $this->projects[$uriString] = $project;

        yield $this->eventDispatcher->dispatch(OnProjectOpen::class, $project);

        return $project;
    }

    public function closeProject(Project $project): \Generator
    {
        $uriString = (string)$project->getRootUri();
        unset($this->projects[$uriString]);
        $project->close();

        yield $this->eventDispatcher->dispatch(OnProjectClose::class, $project);
    }

    public function closeAll(): \Generator
    {
        foreach ($this->documents as $document) {
            yield $this->close($document);
        }

        foreach ($this->projects as $project) {
            yield $this->closeProject($project);
        }
    }
}
