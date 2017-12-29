<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Logger;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Recoil\Kernel;
use Tsufeki\Tenkawa\Protocol\Client\MessageType;
use Tsufeki\Tenkawa\Protocol\LanguageClient;

class ClientLogger extends AbstractLogger
{
    use LoggerTrait;

    const LEVEL_MAP = [
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
     * @var LanguageClient
     */
    private $client;

    /**
     * @var Kernel
     */
    private $kernel;

    public function __construct(LanguageClient $client, Kernel $kernel)
    {
        $this->client = $client;
        $this->kernel = $kernel;
    }

    public function log($level, $message, array $context = [])
    {
        $this->kernel->execute(function () use ($level, $message, $context) {
            yield $this->client->logMessage(
                static::LEVEL_MAP[$level],
                trim($this->interpolate($message, $context) . "\n" . ($context['exception'] ?? ''))
            );
        });
    }
}
