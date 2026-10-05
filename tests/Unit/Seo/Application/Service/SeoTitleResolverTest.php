<?php

declare(strict_types=1);

namespace App\Tests\Unit\Seo\Application\Service;

use App\Module\Seo\Application\Service\SeoTitleResolver;
use App\Module\Settings\Application\Service\SettingsRegistry;
use App\Module\Settings\Application\Service\SettingsService;
use App\Module\Settings\Domain\Entity\Setting;
use App\Module\Settings\Domain\Repository\SettingRepositoryInterface;
use App\Shared\Application\Logging\BusinessEventLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class SeoTitleResolverTest extends TestCase
{
    public function testReturnsPageTitleWhenNoMetaTitleAndNoTemplate(): void
    {
        self::assertSame('Главная', $this->resolver()->resolve('Главная', 'Заборы', null));
    }

    public function testExplicitMetaTitleWinsOverTemplate(): void
    {
        $resolver = $this->resolver(['title_template' => '{h1} | {site_name}']);

        self::assertSame('Свой SEO title', $resolver->resolve('Главная', 'Заборы', '  Свой SEO title  '));
    }

    public function testBlankMetaTitleFallsBackToTemplate(): void
    {
        $resolver = $this->resolver(['title_template' => '{h1} — заборы в Москве | {site_name}']);

        self::assertSame('Заборы — заборы в Москве | ЗаборПрофиль', $resolver->resolve('Главная', 'Заборы', '   '));
    }

    public function testTemplateSupportsTitleH1AndConfiguredSiteName(): void
    {
        $resolver = $this->resolver([
            'title_template' => '{title} / {h1} / {site_name}',
            'site_name' => 'Забор-Про',
        ]);

        self::assertSame('Ворота / Откатные ворота / Забор-Про', $resolver->resolve('Ворота', 'Откатные ворота', null));
    }

    public function testEmptyRenderedTemplateFallsBackToTitle(): void
    {
        $resolver = $this->resolver(['title_template' => '{site_name}', 'site_name' => '  ']);

        self::assertSame('Главная', $resolver->resolve('Главная', 'Заборы', null));
    }

    /**
     * @param array<string, string> $seoSettings
     */
    private function resolver(array $seoSettings = []): SeoTitleResolver
    {
        $repository = new class () implements SettingRepositoryInterface {
            /** @var array<string, Setting> */
            private array $items = [];

            public function save(Setting $setting): void
            {
                $this->items[$setting->scope().'.'.$setting->key()] = $setting;
            }

            public function remove(Setting $setting): void
            {
                unset($this->items[$setting->scope().'.'.$setting->key()]);
            }

            public function findOne(string $scope, string $key): ?Setting
            {
                return $this->items[$scope.'.'.$key] ?? null;
            }

            public function findByScope(?string $scope = null): array
            {
                return array_values($this->items);
            }
        };
        $service = new SettingsService($repository, new ArrayAdapter(), new BusinessEventLogger(new NullLogger()));
        foreach ($seoSettings as $key => $value) {
            $service->set('seo', $key, $value);
        }

        return new SeoTitleResolver(new SettingsRegistry($service));
    }
}
