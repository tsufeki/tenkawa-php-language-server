<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\PhpStan\Analyser;

use Tsufeki\Tenkawa\Server\Utils\Cache;

interface AnalysedCacheAware
{
    public function setCache(?Cache $cache): void;
}
