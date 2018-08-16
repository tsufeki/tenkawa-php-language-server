<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Utils\PriorityKernel;

use Recoil\Kernel\StrandTrait;
use Recoil\Kernel\SystemStrand;

class PriorityStrand implements SystemStrand
{
    use StrandTrait;

    /**
     * @var int
     */
    private $priority = 0;

    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * @return $this
     */
    public function setPriority(int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }
}
