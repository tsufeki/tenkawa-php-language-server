<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Io;

use Recoil\Recoil;
use Tsufeki\Tenkawa\Server\Exception\IoException;
use Tsufeki\Tenkawa\Server\Uri;
use Tsufeki\Tenkawa\Server\Utils\Throttler;

class LocalFileReader implements FileReader
{
    private const MAX_SIZE = 1 * 1024 * 1024;

    private const MAX_CONCURRENT = 30;

    /**
     * @var Throttler
     */
    private $throttler;

    public function __construct()
    {
        $this->throttler = new Throttler(self::MAX_CONCURRENT);
    }

    public function read(Uri $uri): \Generator
    {
        $job = function () use ($uri): \Generator {
            $file = false;

            try {
                $file = @fopen($uri->getFilesystemPath(), 'r');
                if ($file === false) {
                    throw new IoException("Can't open file $uri");
                }

                stream_set_blocking($file, false);

                $content = yield Recoil::read($file, self::MAX_SIZE + 1, self::MAX_SIZE + 1);
                if (strlen($content) > self::MAX_SIZE) {
                    throw new IoException("File size limit exceeded for $uri");
                }
            } finally {
                if (is_resource($file)) {
                    @fclose($file);
                }
            }

            return $content;
        };

        return yield $this->throttler->run($job);
    }
}
