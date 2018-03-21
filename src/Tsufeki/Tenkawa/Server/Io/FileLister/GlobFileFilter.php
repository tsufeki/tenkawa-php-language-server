<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Io\FileLister;

use Webmozart\Glob\Glob;
use Webmozart\PathUtil\Path;

class GlobFileFilter implements FileFilter
{
    /**
     * @var string
     */
    private $glob;

    /**
     * @var string
     */
    private $fileType;

    public function __construct(string $glob, string $fileType)
    {
        $this->glob = $glob;
        $this->fileType = $fileType;
    }

    public function filter(string $uri, string $baseUri): int
    {
        if (Glob::match($uri, Path::join($baseUri, $this->glob))) { // TODO: windows support
            return self::ACCEPT;
        }

        return self::ABSTAIN;
    }

    public function getFileType(): string
    {
        return $this->fileType;
    }

    public function enterDirectory(string $uri, string $baseUri): int
    {
        return self::ACCEPT;
    }
}
