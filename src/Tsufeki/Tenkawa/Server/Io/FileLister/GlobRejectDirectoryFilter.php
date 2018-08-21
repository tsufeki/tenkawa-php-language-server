<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Io\FileLister;

use Webmozart\Glob\Glob;
use Webmozart\PathUtil\Path;

class GlobRejectDirectoryFilter implements FileFilter
{
    /**
     * @var string
     */
    private $glob;

    public function __construct(string $glob)
    {
        $this->glob = $glob;
    }

    public function filter(string $uri, string $baseUri): int
    {
        if (Glob::match($uri, Path::join($baseUri, $this->glob, '**/*'))) {
            return self::REJECT;
        }

        return self::ABSTAIN;
    }

    public function getFileType(): string
    {
        return '';
    }

    public function enterDirectory(string $uri, string $baseUri): int
    {
        if (Glob::match($uri, Path::join($baseUri, $this->glob . '{,/**/*}'))) {
            return self::REJECT;
        }

        return self::ABSTAIN;
    }
}
