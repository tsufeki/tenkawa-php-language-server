<?php declare(strict_types=1);

namespace Tests\Tsufeki\Tenkawa;

use PHPUnit\Framework\TestCase;
use Recoil\React\ReactKernel;
use Tsufeki\BlancheJsonRpc\Transport\TransportMessageObserver;
use Tsufeki\Tenkawa\Exception\TransportException;
use Tsufeki\Tenkawa\Transport;

/**
 * @covers \Tsufeki\Tenkawa\Transport
 */
class TransportTest extends TestCase
{
    public function test_send()
    {
        $readStream = tmpfile();
        $writeStream = tmpfile();
        stream_set_blocking($readStream, false);
        stream_set_blocking($writeStream, false);

        $transport = new Transport($readStream, $writeStream, []);

        ReactKernel::start(function () use ($transport) {
            yield $transport->send('foobar');
        });

        rewind($writeStream);

        $expected = "Content-Length: 6\r\n\r\nfoobar";
        $this->assertSame($expected, stream_get_contents($writeStream));
    }

    /**
     * @dataProvider receive_data
     */
    public function test_receive(string $message, string $expected)
    {
        $readStream = tmpfile();
        $writeStream = tmpfile();

        fwrite($readStream, $message);
        rewind($readStream);

        stream_set_blocking($readStream, false);
        stream_set_blocking($writeStream, false);

        $transport = new Transport($readStream, $writeStream, []);

        $observer = $this->createMock(TransportMessageObserver::class);
        $observer
            ->expects($this->once())
            ->method('receive')
            ->with($this->identicalTo($expected))
            ->willReturn((function () { yield; })());

        $transport->attach($observer);

        ReactKernel::start(function () use ($transport) {
            yield $transport->receive();
        });
    }

    public function receive_data(): array
    {
        $long = str_repeat('=-', 6000);

        return [
            ["Content-Length: 6\r\n\r\nfoobar", 'foobar'],
            ['Content-Length: ' . strlen($long) . "\r\n\r\n" . $long, $long],
        ];
    }

    /**
     * @dataProvider receive_errors_data
     */
    public function test_receive_errors(string $message)
    {
        $readStream = tmpfile();
        $writeStream = tmpfile();

        fwrite($readStream, $message);
        rewind($readStream);

        stream_set_blocking($readStream, false);
        stream_set_blocking($writeStream, false);

        $transport = new Transport($readStream, $writeStream, []);

        $this->expectException(TransportException::class);

        ReactKernel::start(function () use ($transport) {
            yield $transport->receive();
        });
    }

    public function receive_errors_data(): array
    {
        $long = str_repeat('=-', 6000);

        return [
            // Content too short
            ["Content-Length: 6\r\n\r\nfoo"],
            ['Content-Length: ' . (strlen($long) + 1) . "\r\n\r\n" . $long],

            // Headers too short
            ["Content-Length: 6\r\nX-Foo: 1"],

            // Headers too big
            ['Content-Length: ' . str_repeat('0', 6000) . "\r\n\r\n"],
        ];
    }

    public function test_run()
    {
        $readStream = tmpfile();
        $writeStream = tmpfile();

        fwrite($readStream, "Content-Length: 3\r\nX-Foo: 2\r\n\r\nfooContent-Length: 6\r\n\r\nbarbaz");
        rewind($readStream);

        stream_set_blocking($readStream, false);
        stream_set_blocking($writeStream, false);

        $transport = new Transport($readStream, $writeStream, []);

        $observer = $this->createMock(TransportMessageObserver::class);
        $observer
            ->expects($this->exactly(2))
            ->method('receive')
            ->withConsecutive(
                [$this->identicalTo('foo')],
                [$this->identicalTo('barbaz')]
            )
            ->willReturnOnConsecutiveCalls(
                (function () { yield; })(),
                (function () { yield; })()
            );

        $transport->attach($observer);

        $this->expectException(TransportException::class);

        ReactKernel::start(function () use ($transport) {
            yield $transport->run();
        });
    }
}
