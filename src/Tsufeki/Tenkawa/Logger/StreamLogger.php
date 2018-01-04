<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Logger;

use Psr\Log\AbstractLogger;

class StreamLogger extends AbstractLogger
{
    use LoggerTrait;

    /**
     * @var resource
     */
    private $stream;

    /**
     * @param resource $stream
     */
    public function __construct($stream)
    {
        $this->stream = $stream;
    }

    public function log($level, $message, array $context = [])
    {
        $context['date'] = date(\DateTime::ATOM);
        $context['level'] = strtoupper($level);
        $context['exception'] = isset($context['exception']) ? strtr((string)$context['exception'], "\n", "\n    ") : '';

        fwrite($this->stream, trim($this->interpolate("{date} {level} $message\n{exception}", $context)) . "\n");
    }
}
