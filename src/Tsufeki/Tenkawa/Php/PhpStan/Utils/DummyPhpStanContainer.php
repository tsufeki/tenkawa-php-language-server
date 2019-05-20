<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\PhpStan\Utils;

use PHPStan\DependencyInjection\Container;
use PHPStan\ShouldNotHappenException;

class DummyPhpStanContainer implements Container
{
    /**
     * @var string[]
     */
    private $dynamicConstantNames;

    /**
     * @param string[] $dynamicConstantNames
     */
    public function __construct(array $dynamicConstantNames)
    {
        $this->dynamicConstantNames = $dynamicConstantNames;
    }

    public function getService(string $serviceName)
    {
        throw new ShouldNotHappenException();
    }

    public function getByType(string $className)
    {
        throw new ShouldNotHappenException();
    }

    public function getServicesByTag(string $tagName): array
    {
        throw new ShouldNotHappenException();
    }

    public function getParameter(string $parameterName)
    {
        if ($parameterName === 'dynamicConstantNames') {
            return $this->dynamicConstantNames;
        }

        throw new ShouldNotHappenException();
    }
}
