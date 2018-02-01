<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\ProcessRunner;

use Tsufeki\Tenkawa\Server\Utils\Throttler;

class ThrottledProcessRunner implements ProcessRunner
{
    /**
     * @var ProcessRunner
     */
    private $innerProcessRunner;

    /**
     * @var Throttler
     */
    private $throttler;

    public function __construct(ProcessRunner $innerProcessRunner, Throttler $throttler)
    {
        $this->innerProcessRunner = $innerProcessRunner;
        $this->throttler = $throttler;
    }

    public function run(array $cmd, string $stdin = null): \Generator
    {
        return yield $this->throttler->run($this->innerProcessRunner->run($cmd, $stdin));
    }
}
