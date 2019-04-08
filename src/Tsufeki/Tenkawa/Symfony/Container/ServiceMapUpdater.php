<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Symfony\Container;

use PHPStan\Symfony\ServiceMap;
use Tsufeki\Tenkawa\Php\PhpStan\Analyser\AnalysedProjectAware;
use Tsufeki\Tenkawa\Server\Document\Project;

class ServiceMapUpdater implements AnalysedProjectAware
{
    /**
     * @var ServiceMap
     */
    private $serviceMap;

    /**
     * @var ServiceMapWatcher
     */
    private $serviceMapWatcher;

    /**
     * @var \ReflectionProperty
     */
    private $reflectionProperty;

    public function __construct(ServiceMap $serviceMap, ServiceMapWatcher $serviceMapWatcher)
    {
        $this->serviceMap = $serviceMap;
        $this->serviceMapWatcher = $serviceMapWatcher;
        $this->reflectionProperty = (new \ReflectionClass(ServiceMap::class))->getProperty('services');
        $this->reflectionProperty->setAccessible(true);
    }

    public function setProject(?Project $project): void
    {
        $newServiceMap = null;
        if ($project !== null) {
            $newServiceMap = $this->serviceMapWatcher->getServiceMap($project);
        }

        $this->setServiceMap($newServiceMap);
    }

    private function setServiceMap(?ServiceMap $newServiceMap): void
    {
        $services = [];
        if ($newServiceMap !== null) {
            $services = $this->reflectionProperty->getValue($newServiceMap);
        }

        $this->reflectionProperty->setValue($this->serviceMap, $services);
    }
}
