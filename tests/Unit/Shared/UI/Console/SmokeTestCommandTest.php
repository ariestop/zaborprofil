<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\UI\Console;

use App\Shared\UI\Console\SmokeTestCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

final class SmokeTestCommandTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        $this->temporaryDirectory = sys_get_temp_dir().'/zaborprofil-smoke-test-'.bin2hex(random_bytes(4));
        mkdir($this->temporaryDirectory.'/public_html/build/.vite', 0775, true);
        mkdir($this->temporaryDirectory.'/public_html/uploads', 0775, true);
        file_put_contents($this->temporaryDirectory.'/public_html/build/.vite/manifest.json', '{}');
        $_ENV['APP_SECRET'] = 'test-secret';
        $_ENV['DATABASE_URL'] = 'sqlite:///:memory:';
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->temporaryDirectory);
        unset($_ENV['APP_SECRET'], $_ENV['DATABASE_URL']);
    }

    public function testSmokeCommandPassesWhenReleaseReadinessChecksAreSatisfied(): void
    {
        $tester = new CommandTester(new SmokeTestCommand($this->router(), $this->temporaryDirectory));

        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Smoke test passed.', $tester->getDisplay());
    }

    private function router(): RouterInterface
    {
        $routes = new RouteCollection();
        foreach ([
            'health_check',
            'admin_login',
            'admin_spa',
            'api_leads_create',
            'admin_api_media_assets_index',
            'admin_api_menu_item_index',
            'admin_api_leads_index',
            'public_sitemap_xml',
            'public_robots_txt',
        ] as $name) {
            $routes->add($name, new Route('/'.$name));
        }

        return new readonly class ($routes) implements RouterInterface {
            public function __construct(private RouteCollection $routes)
            {
            }

            public function getRouteCollection(): RouteCollection
            {
                return $this->routes;
            }

            public function setContext(\Symfony\Component\Routing\RequestContext $context): void
            {
            }

            public function getContext(): \Symfony\Component\Routing\RequestContext
            {
                return new \Symfony\Component\Routing\RequestContext();
            }

            /**
             * @param array<string, mixed> $parameters
             */
            public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
            {
                return '/'.$name;
            }

            /**
             * @return array<string, mixed>
             */
            public function match(string $pathinfo): array
            {
                return [];
            }
        };
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory.'/'.$item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } elseif (is_file($path)) {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
