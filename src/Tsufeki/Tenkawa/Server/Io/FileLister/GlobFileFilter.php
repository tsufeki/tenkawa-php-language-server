<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Io\FileLister;

use Tsufeki\Tenkawa\Server\Utils\StringUtils;
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

    /**
     * @var int
     */
    private $action;

    public function __construct(string $glob, string $fileType, int $action = self::ACCEPT)
    {
        $this->glob = $glob;
        $this->fileType = $fileType;
        $this->action = $action;
    }

    public function filter(string $uri, string $baseUri): int
    {
        if (Glob::match($uri, Path::join($baseUri, $this->glob))) {
            return $this->action;
        }

        return self::ABSTAIN;
    }

    public function getFileType(): string
    {
        return $this->fileType;
    }

    public function enterDirectory(string $uri, string $baseUri): int
    {
        $dir = Glob::getBasePath(Path::join($baseUri, $this->glob));
        if ($dir === $uri || StringUtils::startsWith($uri, $dir . '/') || StringUtils::startsWith($dir, $uri . '/')) {
            return self::ACCEPT;
        }

        return self::ABSTAIN;
    }
}
