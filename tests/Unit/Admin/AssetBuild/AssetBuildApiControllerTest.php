<?php

declare(strict_types=1);

namespace App\Tests\Unit\Admin\AssetBuild;

use App\Kernel;
use App\Module\Admin\Application\Service\AssetBuildRunner;
use App\Module\Admin\UI\Admin\AssetBuildApiController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class AssetBuildApiControllerTest extends TestCase
{
    public function testStatusIsNotFoundWhenBuildIsDisabled(): void
    {
        $response = $this->controller(enabled: false)->status();

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('ASSET_BUILD_DISABLED', $this->decode($response->getContent())['code']);
    }

    public function testRunIsNotFoundWhenBuildIsDisabled(): void
    {
        $response = $this->controller(enabled: false)->run(new Request(content: '{"targets":["all"]}'));

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('ASSET_BUILD_DISABLED', $this->decode($response->getContent())['code']);
    }

    public function testAccessDeniedWinsOverDisabled(): void
    {
        $response = $this->controller(enabled: false, granted: false)->status();

        self::assertSame(403, $response->getStatusCode());
    }

    public function testRunnerReportsEnabledFlag(): void
    {
        $enabled = new AssetBuildRunner(new Kernel('test', true), 'npm run build', true);
        $disabled = new AssetBuildRunner(new Kernel('test', true), 'npm run build', false);

        self::assertTrue($enabled->isEnabled());
        self::assertFalse($disabled->isEnabled());
    }

    private function controller(bool $enabled, bool $granted = true): AssetBuildApiController
    {
        $checker = $this->createStub(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturn($granted);

        return new AssetBuildApiController(
            new AssetBuildRunner(new Kernel('test', true), 'npm run build', $enabled),
            $checker,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string|false $json): array
    {
        self::assertIsString($json);

        return json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
    }
}
