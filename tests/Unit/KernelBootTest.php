<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Kernel;
use PHPUnit\Framework\TestCase;

final class KernelBootTest extends TestCase
{
    public function testKernelUsesTestEnvironment(): void
    {
        $kernel = new Kernel('test', true);

        self::assertSame('test', $kernel->getEnvironment());
        self::assertTrue($kernel->isDebug());
    }
}
