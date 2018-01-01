<?php declare(strict_types=1);

namespace Tests\Tsufeki\Tenkawa\Fixtures;

use Recoil\Recoil;
use Recoil\Strand;
use Tsufeki\BlancheJsonRpc\Json;
use Tsufeki\BlancheJsonRpc\Transport\TransportMessageObserver;
use Tsufeki\Tenkawa\Transport\RunnableTransport;

class DummyTransport implements RunnableTransport
{
    /**
     * @var TransportMessageObserver
     */
    private $observer;

    /**
     * @var string[]
     */
    private $messages = [];

    /**
     * @var Strand
     */
    private $waitingReceiver;

    public function send(string $message): \Generator
    {
        $this->messages[] = $message;
        if ($this->waitingReceiver) {
            $this->waitingReceiver->send();
        }

        return;
        yield;
    }

    public function attach(TransportMessageObserver $observer)
    {
        $this->observer = $observer;
    }

    public function run(): \Generator
    {
        yield Recoil::suspend();
    }

    public function clientSend($message): \Generator
    {
        yield $this->observer->receive(Json::encode($message));
    }

    public function clientReceive(): \Generator
    {
        if (!$this->messages) {
            yield Recoil::suspend(function (Strand $strand) {
                $this->waitingReceiver = $strand;
            });
        }

        return array_shift($this->messages);
    }
}
