<?php

declare(strict_types=1);

namespace App\Tests\Unit\Config;

use App\Kernel;
use PHPUnit\Framework\TestCase;

/**
 * staging (`APP_ENV=staging`) должен собираться с той же конфигурацией, что и prod (блоки `when@staging: *prod_*`),
 * иначе стенд не проверяет prod-настройки перед выкладкой.
 */
final class StagingConfigParityTest extends TestCase
{
    private const array PARITY_PARAMETERS = [
        'app.vite.dev_server_url',
        'env(PUBLIC_HTTP_CACHE_ENABLED)',
        'env(CONTENT_SECURITY_POLICY_ENFORCED)',
        'app.asset_build_enabled',
    ];

    private ?string $previousViteDevServer = null;

    protected function setUp(): void
    {
        $value = $_SERVER['VITE_DEV_SERVER_URL'] ?? null;
        $this->previousViteDevServer = \is_string($value) ? $value : null;
        // Даже если переменная попала в окружение staging/prod, dev-сервер Vite включаться не должен.
        $_SERVER['VITE_DEV_SERVER_URL'] = 'http://localhost:5173';
    }

    protected function tearDown(): void
    {
        if ($this->previousViteDevServer === null) {
            unset($_SERVER['VITE_DEV_SERVER_URL']);
        } else {
            $_SERVER['VITE_DEV_SERVER_URL'] = $this->previousViteDevServer;
        }
    }

    public function testStagingContainerUsesProductionParameters(): void
    {
        $staging = $this->parameters('staging');
        $prod = $this->parameters('prod');

        self::assertSame('', $staging['app.vite.dev_server_url']);
        self::assertFalse($staging['app.asset_build_enabled']);
        self::assertSame($prod, $staging);
    }

    /**
     * @return array<string, mixed>
     */
    private function parameters(string $environment): array
    {
        $kernel = new Kernel($environment, false);
        $kernel->boot();

        try {
            $container = $kernel->getContainer();
            $parameters = [];
            foreach (self::PARITY_PARAMETERS as $name) {
                $parameters[$name] = $container->hasParameter($name) ? $container->getParameter($name) : null;
            }

            return $parameters;
        } finally {
            $kernel->shutdown();
        }
    }
}
