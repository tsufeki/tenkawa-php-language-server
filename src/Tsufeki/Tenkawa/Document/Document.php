<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Document;

use Tsufeki\Tenkawa\Uri;
use Tsufeki\Tenkawa\Utils\KeyValueStateTrait;

class Document
{
    use KeyValueStateTrait;

    /**
     * @var Uri
     */
    private $uri;

    /**
     * @var int|null
     */
    private $version = null;

    /**
     * @var string
     */
    private $language;

    /**
     * @var string
     */
    private $text = '';

    /**
     * @var Project
     */
    private $project;

    public function __construct(Uri $uri, string $language, Project $project)
    {
        $this->uri = $uri;
        $this->language = $language;
        $this->project = $project;
    }

    public function getUri(): Uri
    {
        return $this->uri;
    }

    /**
     * @return int|null
     */
    public function getVersion()
    {
        return $this->version;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getProject(): Project
    {
        return $this->project;
    }

    public function update(string $text, int $version = null): self
    {
        $this->text = $text;
        $this->version = $version;
        $this->data = [];

        return $this;
    }
}
