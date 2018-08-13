<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Io;

use Recoil\Recoil;
use Tsufeki\Tenkawa\Server\Exception\IoException;
use Tsufeki\Tenkawa\Server\Uri;

class LocalFileReader implements FileReader
{
    private const MAX_SIZE = 1 * 1024 * 1024;

    public function read(Uri $uri): \Generator
    {
        $file = @fopen($uri->getFilesystemPath(), 'r');
        if ($file === false) {
            throw new IoException("Can't open file $uri");
        }

        stream_set_blocking($file, false);

        $content = yield Recoil::read($file, self::MAX_SIZE + 1, self::MAX_SIZE + 1);
        if (strlen($content) > self::MAX_SIZE) {
            throw new IoException("File size limit exceeded for $uri");
        }

        return $content;
    }
}
