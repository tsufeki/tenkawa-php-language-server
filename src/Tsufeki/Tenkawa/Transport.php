<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa;

use Recoil\Recoil;
use Tsufeki\BlancheJsonRpc\Transport\Transport as RpcTransport;
use Tsufeki\BlancheJsonRpc\Transport\TransportMessageObserver;

class Transport implements RpcTransport
{
    /**
     * @var resource
     */
    private $readStream;

    /**
     * @var resource
     */
    private $writeStream;

    /**
     * @var string[]
     */
    private $headers;

    /**
     * @var TransportMessageObserver[]
     */
    private $observers = [];

    /**
     * @var string
     */
    private $buffer = '';

    const EOL = "\r\n";
    const HEADER_SEP = ': ';
    const CONTENT_LENGTH = 'Content-Length';
    const MAX_HEADERS_SIZE = 4096;

    // TODO logger

    /**
     * @param resource $readStream
     * @param resource $writeStream
     * @param string[] $headers
     */
    public function __construct(
        $readStream,
        $writeStream,
        array $headers = ['Content-Type' => 'application/vscode-jsonrpc; charset=utf-8']
    ) {
        $this->readStream = $readStream;
        $this->writeStream = $writeStream;
        $this->headers = $headers;
    }

    public function attach(TransportMessageObserver $observer)
    {
        $this->observers[] = $observer;
    }

    public function send(string $message): \Generator
    {
        $headers = $this->headers;
        $headers[self::CONTENT_LENGTH] = strlen($message);

        $buffer = '';
        foreach ($headers as $header => $value) {
            $buffer .= $header . self::HEADER_SEP . $value . self::EOL;
        }

        $buffer .= self::EOL . $message;

        yield Recoil::write($this->writeStream, $buffer);
    }

    public function receive(): \Generator
    {
        $headers = [];

        while (true) {
            $toRead = self::MAX_HEADERS_SIZE - strlen($this->buffer);
            if ($toRead === 0) {
                throw new \RuntimeException('Headers too big');
            }

            $data = yield Recoil::read($this->readStream, 1, $toRead);
            if (!$data) {
                throw new \RuntimeException('Input stream closed');
            }

            $this->buffer .= $data;

            while (strpos($this->buffer, self::EOL) !== false) {
                list($line, $this->buffer) = explode(self::EOL, $this->buffer, 2);

                if ($line === '') {
                    break 2;
                }

                list($key, $value) = explode(self::HEADER_SEP, $line, 2);
                $headers[strtolower($key)] = trim($value);
            }
        }

        $length = (int)$headers[strtolower(self::CONTENT_LENGTH)];
        $toRead = $length - strlen($this->buffer);
        if ($toRead > 0) {
            $data = yield Recoil::read($this->readStream, $toRead, $toRead);
            if (!$data) {
                throw new \RuntimeException('Input stream closed');
            }

            $this->buffer .= $data;
        }

        $message = substr($this->buffer, 0, $length);
        $this->buffer = substr($this->buffer, $length);

        yield array_map(function (TransportMessageObserver $observer) use ($message) {
            yield $observer->receive($message);
        }, $this->observers);
    }

    public function run(): \Generator
    {
        while (true) {
            yield $this->receive();
        }
    }
}
