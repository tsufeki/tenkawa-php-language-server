<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Logger;

use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class LevelFilteringLogger extends AbstractLogger
{
    private const LEVEL_NUMBER = [
        LogLevel::EMERGENCY => 8,
        LogLevel::ALERT => 7,
        LogLevel::CRITICAL => 6,
        LogLevel::ERROR => 5,
        LogLevel::WARNING => 4,
        LogLevel::NOTICE => 3,
        LogLevel::INFO => 2,
        LogLevel::DEBUG => 1,
    ];

    /**
     * @var LoggerInterface
     */
    private $inner;

    /**
     * @var int
     */
    private $levelNumber;

    public function __construct(LoggerInterface $inner, string $level)
    {
        $this->inner = $inner;
        $this->levelNumber = $this->getLevelNumber($level);
    }

    private function getLevelNumber(string $level): int
    {
        return self::LEVEL_NUMBER[$level] ?? self::LEVEL_NUMBER[LogLevel::DEBUG];
    }

    public function log($level, $message, array $context = [])
    {
        if ($this->getLevelNumber($level) >= $this->levelNumber) {
            $this->inner->log($level, $message, $context);
        }
    }
}
