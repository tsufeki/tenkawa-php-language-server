<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Transport;

use Tsufeki\BlancheJsonRpc\Transport\Transport;

interface RunnableTransport extends Transport
{
    /**
     * Receive messages indefinitely (until stream is closed or Recoil stops).
     */
    public function run(): \Generator;
}
