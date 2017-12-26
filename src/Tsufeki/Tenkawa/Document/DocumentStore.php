<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Document;

use Tsufeki\Tenkawa\Event\Document\OnChange;
use Tsufeki\Tenkawa\Event\Document\OnClose;
use Tsufeki\Tenkawa\Event\Document\OnOpen;
use Tsufeki\Tenkawa\Exception\DocumentNotOpenException;
use Tsufeki\Tenkawa\Uri;

class DocumentStore
{
    /**
     * @var Document[]
     */
    private $documents = [];

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
     * @param OnOpen[]   $onOpen
     * @param OnChange[] $onChange
     * @param OnClose[]  $onClose
     */
    public function __construct(array $onOpen, array $onChange, array $onClose)
    {
        $this->onOpen = $onOpen;
        $this->onChange = $onChange;
        $this->onClose = $onClose;
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
        $document = new Document($uri, $language);
        $document->update($text, $version);
        $uriString = (string)$document->getUri();
        $this->documents[$uriString] = $document;

        yield array_map(function (OnOpen $onOpen) use ($document) {
            return $onOpen->onOpen($document);
        }, $this->onOpen);

        return $document;
    }

    public function update(Document $document, string $text, int $version = null): \Generator
    {
        $document->update($text, $version);

        yield array_map(function (OnChange $onChange) use ($document) {
            return $onChange->onChange($document);
        }, $this->onChange);
    }

    public function close(Document $document): \Generator
    {
        $uriString = (string)$document->getUri();
        unset($this->documents[$uriString]);
        $document->close();

        yield array_map(function (OnClose $onClose) use ($document) {
            return $onClose->onClose($document);
        }, $this->onClose);
    }

    public function closeAll()
    {
        foreach ($this->documents as $document) {
            $this->close($document);
        }
    }
}
