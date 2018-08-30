<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Composer;

use Tsufeki\Tenkawa\Server\Io\FileLister\FileFilter;
use Tsufeki\Tenkawa\Server\Uri;
use Webmozart\Glob\Glob;

class ComposerFileFilter implements FileFilter
{
    /**
     * @var string[]
     */
    private $rejectGlobs;

    /**
     * @var string[]
     */
    private $acceptGlobs;

    /**
     * @var string[]
     */
    private $forceRejectGlobs;

    /**
     * @param string[] $rejectGlobs
     * @param string[] $acceptGlobs
     * @param string[] $forceRejectGlobs
     */
    public function __construct(array $rejectGlobs, array $acceptGlobs, array $forceRejectGlobs)
    {
        $this->rejectGlobs = $this->normalize($rejectGlobs);
        $this->acceptGlobs = $this->normalize($acceptGlobs);
        $this->forceRejectGlobs = $this->normalize($forceRejectGlobs);
    }

    private function normalize(array $globs): array
    {
        return array_map(function ($glob) {
            return Uri::fromString($glob)->getNormalizedGlob();
        }, array_unique($globs));
    }

    public function filter(string $uri, string $baseUri): int
    {
        $accept = !$this->matchArray($this->rejectGlobs, $uri)
            || ($this->matchArray($this->acceptGlobs, $uri)
            && !$this->matchArray($this->forceRejectGlobs, $uri));

        return $accept ? self::ABSTAIN : self::REJECT;
    }

    public function getFileType(): string
    {
        return '';
    }

    public function enterDirectory(string $uri, string $baseUri): int
    {
        return self::ABSTAIN;
    }

    private function matchArray(array $globs, string $uri): bool
    {
        foreach ($globs as $glob) {
            if (Glob::match($uri, $glob)) {
                return true;
            }
        }

        return false;
    }
}
