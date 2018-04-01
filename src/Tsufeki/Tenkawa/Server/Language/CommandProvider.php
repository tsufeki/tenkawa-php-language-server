<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Language;

/**
 * @method \Generator execute(...$args)
 */
interface CommandProvider
{
    public function getCommand(): string;
}
