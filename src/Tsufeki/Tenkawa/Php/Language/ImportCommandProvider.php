<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Language;

use Tsufeki\Tenkawa\Server\Language\CommandProvider;
use Tsufeki\Tenkawa\Server\Protocol\Common\Position;
use Tsufeki\Tenkawa\Server\Uri;

class ImportCommandProvider implements CommandProvider
{
    const COMMAND = 'tenkawa.php.refactor.import';

    public function getCommand(): string
    {
        return self::COMMAND;
    }

    public function execute(Uri $uri, Position $position, string $kind, string $name): \Generator
    {
        yield;
    }
}
