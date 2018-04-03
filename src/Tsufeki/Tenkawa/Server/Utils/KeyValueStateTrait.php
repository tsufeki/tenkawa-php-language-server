<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Utils;

trait KeyValueStateTrait
{
    /**
     * @var bool
     */
    private $closed = false;

    /**
     * @var mixed[]
     */
    private $data = [];

    public function get(string $key)
    {
        return $this->data[$key] ?? null;
    }

    public function set(string $key, $data): self
    {
        $this->data[$key] = $data;

        return $this;
    }

    public function clear()
    {
        $this->data = [];
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    public function close()
    {
        $this->closed = true;
        $this->clear();
    }
}
