<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Document;

use Tsufeki\Tenkawa\Event\Document\OnChange;
use Tsufeki\Tenkawa\Event\Document\OnClose;
use Tsufeki\Tenkawa\Event\Document\OnOpen;
use Tsufeki\Tenkawa\Event\Document\OnProjectClose;
use Tsufeki\Tenkawa\Event\Document\OnProjectOpen;
use Tsufeki\Tenkawa\Exception\DocumentNotOpenException;
use Tsufeki\Tenkawa\Exception\ProjectNotOpenException;
use Tsufeki\Tenkawa\Uri;

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
     * @var OnOpen[]
     */
    private $onOpen;

    /**
     * @var OnChange[]
     */
    private $onChange;

    /**
     * @var OnClose[]
     */
    private $onClose;

    /**
     * @var OnProjectOpen[]
     */
    private $onProjectOpen;

    /**
     * @var OnProjectClose[]
     */
    private $onProjectClose;

    /**
     * @param OnOpen[]         $onOpen
     * @param OnChange[]       $onChange
     * @param OnClose[]        $onClose
     * @param OnProjectOpen[]  $onProjectOpen
     * @param OnProjectClose[] $onProjectClose
     */
    public function __construct(
        array $onOpen,
        array $onChange,
        array $onClose,
        array $onProjectOpen,
        array $onProjectClose
    ) {
        $this->onOpen = $onOpen;
        $this->onChange = $onChange;
        $this->onClose = $onClose;
        $this->onProjectOpen = $onProjectOpen;
        $this->onProjectClose = $onProjectClose;
    }

    /**
     * @throws DocumentNotOpenException
     */
    public function get(Uri $uri): Document
    {
        $uriString = (string)$uri;
        if (!isset($this->documents[$uriString])) {
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

        yield array_map(function (OnOpen $onOpen) use ($document) {
            return $onOpen->onOpen($document);
        }, $this->onOpen);

        return $document;
    }

    /**
     * Get document but don't track it as open.
     *
     * @resolve Document
     */
    public function load(Uri $uri, string $language, string $text): \Generator
    {
        $project = yield $this->getProjectForUri($uri);
        $document = new Document($uri, $language, $project);
        $document->update($text);

        return $document;
    }

    public function update(Document $document, string $text, int $version = null): \Generator
    {
        // Check if open
        $this->get($document->getUri());

        $document->update($text, $version);

        yield array_map(function (OnChange $onChange) use ($document) {
            return $onChange->onChange($document);
        }, $this->onChange);
    }

    public function close(Document $document): \Generator
    {
        // Check if open
        $this->get($document->getUri());

        $uriString = (string)$document->getUri();
        unset($this->documents[$uriString]);
        $document->getProject()->removeDocument($document);
        $document->close();

        yield array_map(function (OnClose $onClose) use ($document) {
            return $onClose->onClose($document);
        }, $this->onClose);
    }

    /**
     * @throws ProjectNotOpenException
     */
    public function getProject(Uri $rootUri): Project
    {
        $uriString = (string)$rootUri;
        if (!isset($this->projects[$uriString])) {
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

        yield array_map(function (OnProjectOpen $onProjectOpen) use ($project) {
            return $onProjectOpen->onProjectOpen($project);
        }, $this->onProjectOpen);

        return $project;
    }

    public function closeProject(Project $project): \Generator
    {
        $uriString = (string)$project->getRootUri();
        unset($this->projects[$uriString]);
        $project->close();

        yield array_map(function (OnProjectClose $onProjectClose) use ($project) {
            return $onProjectClose->onProjectClose($project);
        }, $this->onProjectClose);
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
