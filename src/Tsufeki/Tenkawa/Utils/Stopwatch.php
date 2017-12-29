<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Utils;

class Stopwatch
{
    /**
     * @var float
     */
    private $start;

    public function __construct()
    {
        $this->start = $this->now();
    }

    public function __toString(): string
    {
        $seconds = $this->now() - $this->start;
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
        $result .= sprintf('%.1fs', $seconds);

        return $result;
    }

    private function now(): float
    {
        return microtime(true);
    }
}
