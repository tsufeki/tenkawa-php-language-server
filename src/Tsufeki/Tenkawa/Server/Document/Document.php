<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Document;

use Tsufeki\Tenkawa\Server\Uri;

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

    public function update(string $text, int $version = null): self
    {
        $this->text = $text;
        $this->version = $version;
        $this->data = [];

        return $this;
    }
}
