<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\Command;

/**
 * @method \Generator execute(...$args)
 */
interface CommandProvider
{
    public function getCommand(): string;
}
