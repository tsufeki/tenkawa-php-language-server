<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Logger;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Recoil\Kernel;
use Tsufeki\Tenkawa\Server\Feature\Message\MessageFeature;
use Tsufeki\Tenkawa\Server\Feature\Message\MessageType;

class ClientLogger extends AbstractLogger
{
    use LoggerTrait;

    private const LEVEL_MAP = [
        LogLevel::EMERGENCY => MessageType::ERROR,
        LogLevel::ALERT => MessageType::ERROR,
        LogLevel::CRITICAL => MessageType::ERROR,
        LogLevel::ERROR => MessageType::ERROR,
        LogLevel::WARNING => MessageType::WARNING,
        LogLevel::NOTICE => MessageType::WARNING,
        LogLevel::INFO => MessageType::INFO,
        LogLevel::DEBUG => MessageType::LOG,
    ];

    /**
     * @var MessageFeature
     */
    private $messageFeature;

    /**
     * @var Kernel
     */
    private $kernel;

    public function __construct(MessageFeature $messageFeature, Kernel $kernel)
    {
        $this->messageFeature = $messageFeature;
        $this->kernel = $kernel;
    }

    public function log($level, $message, array $context = [])
    {
        $this->kernel->execute(function () use ($level, $message, $context) {
            yield $this->messageFeature->logMessage(
                static::LEVEL_MAP[$level],
                trim($this->interpolate($message, $context) . "\n" . ($context['exception'] ?? ''))
            );
        });
    }
}
