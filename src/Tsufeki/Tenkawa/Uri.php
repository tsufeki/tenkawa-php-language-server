<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa;

use Tsufeki\Tenkawa\Exception\UriException;

class Uri
{
    /**
     * @var string|null
     */
    private $scheme;

    /**
     * @var string|null
     */
    private $authority;

    /**
     * @var string|null
     */
    private $path;

    /**
     * @var string|null
     */
    private $query;

    /**
     * @var string|null
     */
    private $fragment;

    const REGEX =
        '~^(?:([a-zA-Z][-a-zA-Z0-9+.]*):)?' .
        '(?://([^/?#]*))?' .
        '([^?#]*)' .
        '(?:\?([^#]*))?' .
        '(?:\#(.*))?\z~s';

    /**
     * @return string|null
     */
    public function getScheme()
    {
        return $this->scheme;
    }

    /**
     * @return string|null
     */
    public function getAuthority()
    {
        return $this->authority;
    }

    /**
     * @return string|null
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @return string|null
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * @return string|null
     */
    public function getFragment()
    {
        return $this->fragment;
    }

    public function __toString(): string
    {
        $result = '';

        if ($this->scheme !== null) {
            $result .= $this->scheme . ':';
        }

        if ($this->authority !== null || $this->scheme === 'file') {
            $result .= '//';
        }

        if ($this->authority !== null) {
            $result .= self::encodeParts($this->authority, ':');
        }

        if ($this->path !== null) {
            $result .= self::encodeParts($this->path, '/');
        }

        if ($this->query !== null) {
            $result .= '?' . str_replace('#', '%23', $this->query);
        }

        if ($this->fragment !== null) {
            $result .= '#' . rawurlencode($this->fragment);
        }

        return $result;
    }

    private static function encodeParts(string $string, string $delimiter): string
    {
        return implode($delimiter, array_map('rawurlencode', explode($delimiter, $string)));
    }

    public function getFilesystemPath(): string
    {
        if (!in_array($this->scheme, ['file', null], true)) {
            throw new UriException('Not a file URI');
        }

        if (!in_array(strtolower((string)$this->authority), ['localhost', ''], true)) {
            throw new UriException("Unsupported authority in a file URI: $this->authority");
        }

        return $this->path ?? '/';
    }

    public static function fromString(string $string): self
    {
        if (preg_match(self::REGEX, $string, $matches) !== 1) {
            throw new UriException("Invalid URI: $string"); // @codeCoverageIgnore
        }

        $uri = new self();

        $uri->scheme = $matches[1] ?: null;
        $uri->authority = self::decodeComponent($matches[2] ?? null);
        $uri->path = self::decodeComponent($matches[3] ?? null);
        $uri->query = self::decodeComponent($matches[4] ?? null, false);
        $uri->fragment = self::decodeComponent($matches[5] ?? null);

        return $uri;
    }

    private static function decodeComponent($component, bool $decode = true)
    {
        if ($component === null || $component === '') {
            return null;
        }

        if ($decode) {
            $component = rawurldecode($component);
        }

        return $component;
    }

    /**
     * @param string $path Absolute path.
     *
     * @return self
     */
    public static function fromFilesystemPath(string $path): self
    {
        $uri = new self();
        $uri->scheme = 'file';

        if ($path === '' || $path[0] !== '/') {
            $path = '/' . $path;
        }
        $uri->path = $path;

        return $uri;
    }
}
