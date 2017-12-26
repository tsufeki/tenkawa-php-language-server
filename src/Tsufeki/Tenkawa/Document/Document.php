<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Document;

use Tsufeki\Tenkawa\Uri;

class Document
{
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
     * @var bool
     */
    private $closed = false;

    /**
     * @var mixed[]
     */
    private $data = [];

    public function __construct(Uri $uri, string $language)
    {
        $this->uri = $uri;
        $this->language = $language;
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

    public function isClosed(): bool
    {
        return $this->closed;
    }

    public function update(string $text, int $version = null): self
    {
        $this->text = $text;
        $this->version = $version;
        $this->data = [];

        return $this;
    }

    public function close()
    {
        $this->closed = true;
        $this->data = [];
        $this->text = '';
    }

    public function get(string $key)
    {
        return $this->data[$key] ?? null;
    }

    public function set(string $key, $data): self
    {
        $this->data[$key] = $data;

        return $this;
    }
}
