<?php declare(strict_types=1);

namespace Tests\Tsufeki\Tenkawa;

use PHPUnit\Framework\TestCase;
use Recoil\React\ReactKernel;
use Tsufeki\BlancheJsonRpc\Transport\TransportMessageObserver;
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

    public function test_receive()
    {
        $readStream = tmpfile();
        $writeStream = tmpfile();

        fwrite($readStream, "Content-Length: 6\r\n\r\nfoobar");
        rewind($readStream);

        stream_set_blocking($readStream, false);
        stream_set_blocking($writeStream, false);

        $transport = new Transport($readStream, $writeStream, []);
        $expected = 'foobar';

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
}
