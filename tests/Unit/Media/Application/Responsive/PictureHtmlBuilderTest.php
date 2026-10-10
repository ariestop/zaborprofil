<?php

declare(strict_types=1);

namespace App\Tests\Unit\Media\Application\Responsive;

use App\Module\Media\Application\Responsive\PictureHtmlBuilder;
use App\Module\Media\Application\Responsive\ResponsiveImage;
use App\Module\Media\Application\Responsive\ResponsiveImageSource;
use PHPUnit\Framework\TestCase;

final class PictureHtmlBuilderTest extends TestCase
{
    public function testBuildsPictureWithAvifAndWebpSourcesSizesAndDimensions(): void
    {
        $html = (new PictureHtmlBuilder())->build('/uploads/media/fence.jpg', $this->image(), [
            'alt' => 'Забор',
            'class' => 'w-full',
            'sizes' => '100vw',
        ]);

        self::assertStringStartsWith('<picture>', $html);
        self::assertStringContainsString('<source type="image/avif" srcset="/uploads/media/variants/fence-320.avif 320w, /uploads/media/variants/fence-768.avif 768w" sizes="(min-resolution: 2.5dppx) calc(100vw * 2 / 3), 100vw">', $html);
        self::assertStringContainsString('<source type="image/webp" srcset="/uploads/media/variants/fence-320.webp 320w" sizes="(min-resolution: 2.5dppx) calc(100vw * 2 / 3), 100vw">', $html);
        self::assertLessThan(strpos($html, 'image/webp'), strpos($html, 'image/avif'));
        self::assertStringContainsString('<img src="/uploads/media/fence.jpg" alt="Забор" width="1600" height="900" class="w-full" loading="lazy" decoding="async">', $html);
        self::assertStringNotContainsString('fetchpriority', $html);
        self::assertStringEndsWith('</picture>', $html);
    }

    public function testHighDensityScreensGetTwoThirdsSlotBeforeOriginalSizes(): void
    {
        $html = (new PictureHtmlBuilder())->build('/uploads/media/fence.jpg', $this->image(), [
            'sizes' => '(min-width: 768px) and (max-width: 1000px) calc(50vw - 16px), (min-width: 1152px) 360px, 100vw',
        ]);

        self::assertStringContainsString(
            'sizes="(min-resolution: 2.5dppx) and ((min-width: 768px) and (max-width: 1000px)) calc(calc(50vw - 16px) * 2 / 3), '
            .'(min-resolution: 2.5dppx) and (min-width: 1152px) calc(360px * 2 / 3), '
            .'(min-resolution: 2.5dppx) calc(100vw * 2 / 3), '
            .'(min-width: 768px) and (max-width: 1000px) calc(50vw - 16px), (min-width: 1152px) 360px, 100vw"',
            $html,
        );
    }

    public function testDefaultSizesAreCappedAndAutoSizesAreKept(): void
    {
        $builder = new PictureHtmlBuilder();

        self::assertStringContainsString(
            'sizes="(min-resolution: 2.5dppx) and (min-width: 1152px) calc(1152px * 2 / 3), (min-resolution: 2.5dppx) calc(100vw * 2 / 3), '.PictureHtmlBuilder::DEFAULT_SIZES.'"',
            $builder->build('/uploads/media/fence.jpg', $this->image()),
        );
        self::assertStringContainsString('sizes="auto, 100vw"', $builder->build('/uploads/media/fence.jpg', $this->image(), ['sizes' => 'auto, 100vw']));
    }

    public function testPriorityImageIsEagerWithHighFetchPriority(): void
    {
        $html = (new PictureHtmlBuilder())->build('/uploads/media/fence.jpg', $this->image(), ['priority' => true]);

        self::assertStringContainsString('loading="eager"', $html);
        self::assertStringContainsString('fetchpriority="high"', $html);
        self::assertStringNotContainsString('loading="lazy"', $html);
    }

    public function testEagerImageHasNoFetchPriority(): void
    {
        $html = (new PictureHtmlBuilder())->build('/uploads/media/fence.jpg', $this->image(), ['eager' => true]);

        self::assertStringContainsString('loading="eager"', $html);
        self::assertStringNotContainsString('fetchpriority', $html);
    }

    public function testFocalPointBecomesObjectPosition(): void
    {
        $html = (new PictureHtmlBuilder())->build('/uploads/media/fence.jpg', $this->image(focalX: 30, focalY: 70));

        self::assertStringContainsString('style="object-position: 30% 70%"', $html);
    }

    public function testAltPrefersExplicitThenAssetThenFallbackAndSupportsDecorative(): void
    {
        $builder = new PictureHtmlBuilder();
        $image = $this->image(alt: 'Alt из медиатеки');

        self::assertStringContainsString('alt="Явный alt"', $builder->build('/x.jpg', $image, ['alt' => 'Явный alt']));
        self::assertStringContainsString('alt="Alt из медиатеки"', $builder->build('/x.jpg', $image, ['alt' => '  ', 'fallback_alt' => 'Блок']));
        self::assertStringContainsString('alt="Блок"', $builder->build('/x.jpg', $this->image(), ['fallback_alt' => 'Блок']));
        self::assertStringContainsString('alt=""', $builder->build('/x.jpg', $image, ['decorative' => true]));
    }

    public function testUnknownSourceFallsBackToPlainLazyImage(): void
    {
        $html = (new PictureHtmlBuilder())->build('https://cdn.example.test/a.jpg?x=1&y=2', null, ['alt' => 'Внешняя']);

        self::assertSame('<img src="https://cdn.example.test/a.jpg?x=1&amp;y=2" alt="Внешняя" loading="lazy" decoding="async">', $html);
    }

    public function testImageWithoutVariantsIsPlainImgWithDimensions(): void
    {
        $image = new ResponsiveImage('/uploads/media/a.png', 100, 50, null, null, null, []);

        self::assertSame(
            '<img src="/uploads/media/a.png" alt="" width="100" height="50" loading="lazy" decoding="async">',
            (new PictureHtmlBuilder())->build('/uploads/media/a.png', $image),
        );
    }

    public function testEscapesAttributesAndIgnoresEmptySource(): void
    {
        $builder = new PictureHtmlBuilder();

        self::assertSame('', $builder->build('  ', null));
        self::assertStringContainsString('alt="&quot;&gt;&lt;script&gt;"', $builder->build('/x.jpg', null, ['alt' => '"><script>']));
    }

    private function image(?string $alt = null, ?int $focalX = null, ?int $focalY = null): ResponsiveImage
    {
        return new ResponsiveImage(
            '/uploads/media/fence.jpg',
            1600,
            900,
            $alt,
            $focalX,
            $focalY,
            [
                new ResponsiveImageSource('image/avif', [320 => '/uploads/media/variants/fence-320.avif', 768 => '/uploads/media/variants/fence-768.avif']),
                new ResponsiveImageSource('image/webp', [320 => '/uploads/media/variants/fence-320.webp']),
            ],
        );
    }
}
