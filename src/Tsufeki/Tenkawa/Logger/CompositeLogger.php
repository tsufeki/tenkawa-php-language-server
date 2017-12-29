<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Logger;

use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

class CompositeLogger extends AbstractLogger
{
    /**
     * @var LoggerInterface[]
     */
    private $loggers = [];

    public function log($level, $message, array $context = [])
    {
        foreach ($this->loggers as $logger) {
            $logger->log($level, $message, $context);
        }
    }

    public function add(LoggerInterface $logger): self
    {
        $this->loggers[] = $logger;

        return $this;
    }

    public function clear(): self
    {
        $this->loggers = [];

        return $this;
    }
}
