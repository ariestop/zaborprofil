<?php

declare(strict_types=1);

namespace App\Tests\Functional\Console;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

final class SystemDiagnosticsCommandTest extends KernelTestCase
{
    public function testDiagnosticsCommandRuns(): void
    {
        self::bootKernel();
        $kernel = self::$kernel;
        self::assertInstanceOf(KernelInterface::class, $kernel);

        $application = new Application($kernel);
        $command = $application->find('app:system:diagnostics');
        $tester = new CommandTester($command);

        $tester->execute([]);

        self::assertStringContainsString('Zaborprofil CMS diagnostics', $tester->getDisplay());
    }
}
