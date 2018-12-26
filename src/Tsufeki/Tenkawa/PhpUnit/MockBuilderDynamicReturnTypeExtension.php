<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\PhpUnit;

use PHPStan\Type\PHPUnit\MockBuilderDynamicReturnTypeExtension as BaseMockBuilderDynamicReturnTypeExtension;

class MockBuilderDynamicReturnTypeExtension extends BaseMockBuilderDynamicReturnTypeExtension
{
    public function getClass(): string
    {
        // Use a hardcorded class to avoid missing class broker exception when
        // trying to find it dynamically.
        return 'PHPUnit\\Framework\\MockObject\\MockBuilder';
    }
}
