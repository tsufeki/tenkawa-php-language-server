<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server;

use Tsufeki\Tenkawa\Server\Exception\UriException;
use Tsufeki\Tenkawa\Server\Utils\Platform;
use Tsufeki\Tenkawa\Server\Utils\StringUtils;

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

    private const REGEX =
        '~^(?:([a-zA-Z][-a-zA-Z0-9+.]*):)?' .
        '(?://([^/?#]*))?' .
        '([^?#]*)' .
        '(?:\?([^#]*))?' .
        '(?:\#(.*))?\z~s';

    public function getScheme(): ?string
    {
        return $this->scheme;
    }

    public function getAuthority(): ?string
    {
        return $this->authority;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function getQuery(): ?string
    {
        return $this->query;
    }

    public function getFragment(): ?string
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
        return implode($delimiter, array_map('rawurlencode', explode($delimiter, $string) ?: []));
    }

    public function isFilesystemPath(): bool
    {
        return in_array($this->scheme, ['file', null], true);
    }

    public function getFilesystemPath(): string
    {
        if (!$this->isFilesystemPath()) {
            throw new UriException("Not a file URI: $this");
        }

        if (!in_array(strtolower((string)$this->authority), ['localhost', ''], true)) {
            throw new UriException("Unsupported authority in a file URI: $this->authority");
        }

        if (Platform::isWindows()) {
            $path = ltrim((string)$this->path, '/');
            $path = str_replace('/', '\\', $path);

            return $path;
        }

        return $this->path ?? '/';
    }

    public static function fromString(string $string): self
    {
        if (!StringUtils::match(self::REGEX, $string, $matches)) {
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

    private static function decodeComponent(?string $component, bool $decode = true): ?string
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

        if (Platform::isWindows()) {
            $path = str_replace('\\', '/', $path);
        }

        if ($path === '' || $path[0] !== '/') {
            $path = '/' . $path;
        }
        $uri->path = $path;

        return $uri;
    }

    public function equals(self $other): bool
    {
        return $this->getNormalized() === $other->getNormalized();
    }

    /**
     * Return normalized form of the URI, which should be suitable to use as array key.
     */
    public function getNormalized(bool $preserveCase = false): string
    {
        $normalized = clone $this;

        if ($normalized->scheme === 'file') {
            if ($normalized->authority !== null && strtolower($normalized->authority) === 'localhost') {
                $normalized->authority = null;
            }

            if ($normalized->path !== null) {
                $normalized->path = rtrim($normalized->path, '/');
            }

            if ($normalized->path === null || $normalized->path === '') {
                $normalized->path = '/';
            }

            if (Platform::isWindows() && !$preserveCase) {
                $normalized->path = strtolower($normalized->path);
            }
        }

        return (string)$normalized;
    }

    public function getWithSlash(): string
    {
        $uriString = (string)$this;

        if (substr($uriString, -1) !== '/') {
            $uriString .= '/';
        }

        return $uriString;
    }

    public function getNormalizedWithSlash(): string
    {
        $normalized = $this->getNormalized();

        if (substr($normalized, -1) !== '/') {
            $normalized .= '/';
        }

        return $normalized;
    }

    public function getNormalizedGlob(): string
    {
        $normalized = $this->getNormalized();
        $normalized = str_replace(['%2a', '%2A'], '*', $normalized);

        return $normalized;
    }

    public function isParentOf(self $other): bool
    {
        if (!in_array($this->scheme, ['file', null], true) || !in_array($other->scheme, ['file', null], true)) {
            return $this->equals($other);
        }

        $thisNormalized = $this->getNormalizedWithSlash();
        $otherNormalized = $other->getNormalized();

        return StringUtils::startsWith($otherNormalized, $thisNormalized);
    }

    public function extractSubpath(self $subUri): ?string
    {
        if (!in_array($this->scheme, ['file', null], true) || !in_array($subUri->scheme, ['file', null], true)) {
            return null;
        }

        $thisNormalized = $this->getNormalizedWithSlash();
        $subNormalized = $subUri->getNormalized();
        $subNormalizedPreservedCase = $subUri->getNormalized(true);

        if (!StringUtils::startsWith($subNormalized, $thisNormalized)) {
            return null;
        }

        return substr($subNormalizedPreservedCase, strlen($thisNormalized));
    }

    public function withLineNumber(int $lineNumber): self
    {
        $uri = clone $this;
        $uri->fragment = "#L$lineNumber";

        return $uri;
    }
}
