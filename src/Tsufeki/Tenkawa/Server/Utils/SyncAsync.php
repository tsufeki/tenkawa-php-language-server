<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Utils;

interface SyncAsync
{
    /**
     * @return mixed
     *
     * @throws \Throwable
     */
    public function callSync(
        callable $syncCallable,
        array $args = [],
        ?callable $resumeCallback = null,
        ?callable $pauseCallback = null
    );

    /**
     * @return mixed
     *
     * @throws \Throwable
     */
    public function callAsync(\Generator $coroutine);
}
