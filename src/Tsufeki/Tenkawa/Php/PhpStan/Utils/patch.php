<?php declare(strict_types=1);

namespace PHPStan\Analyser
{
    if (function_exists('PHPStan\\Analyser\\constant')) {
        return;
    }

    function constant(string $name)
    {
        $broker = \Tsufeki\Tenkawa\Php\PhpStan\IndexReflection\IndexBroker::getInstance();
        if ($broker instanceof \Tsufeki\Tenkawa\Php\PhpStan\IndexReflection\IndexBroker) {
            return $broker->getConstantValue($name);
        }

        return \constant($name);
    }
}
