<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Document;

use Tsufeki\Tenkawa\Uri;
use Tsufeki\Tenkawa\Utils\KeyValueStateTrait;

class Project
{
    use KeyValueStateTrait;

    /**
     * @var Uri
     */
    private $rootUri;

    /**
     * @var Document[]
     */
    private $documents = [];

    public function __construct(Uri $rootUri)
    {
        $this->rootUri = $rootUri;
    }

    public function getRootUri(): Uri
    {
        return $this->rootUri;
    }

    /**
     * @return Document[]
     */
    public function getDocuments(): array
    {
        return $this->documents;
    }

    public function addDocument(Document $document): self
    {
        $this->documents[] = $document;

        return $this;
    }

    public function removeDocument(Document $document): self
    {
        foreach ($this->documents as $i => $d) {
            if ($d === $document) {
                unset($this->documents[$i]);
                break;
            }
        }

        return $this;
    }
}
