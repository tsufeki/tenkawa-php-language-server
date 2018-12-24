<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Utils;

class Stopwatch
{
    /**
     * @var float
     */
    private $start;

    /**
     * @var int
     */
    private $scale;

    public function __construct(int $scale = 1)
    {
        $this->start = $this->now();
        $this->scale = $scale;
    }

    public function getSeconds(): float
    {
        return $this->now() - $this->start;
    }

    public function __toString(): string
    {
        $seconds = $this->getSeconds();
        $minutes = (int)($seconds / 60);
        $seconds = fmod($seconds, 60);
        $hours = (int)($minutes / 60);
        $minutes = $minutes % 60;

        $result = '';
        if ($hours !== 0) {
            $result .= $hours . 'h';
            if ($minutes < 10) {
                $result .= '0';
            }
        }
        if ($hours !== 0 || $minutes !== 0) {
            $result .= $minutes . 'm';
            if ($seconds < 10) {
                $result .= '0';
            }
        }
        $result .= sprintf("%.{$this->scale}fs", $seconds);

        return $result;
    }

    private function now(): float
    {
        return microtime(true);
    }
}
