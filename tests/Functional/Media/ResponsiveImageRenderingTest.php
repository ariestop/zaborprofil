<?php

declare(strict_types=1);

namespace App\Tests\Functional\Media;

use App\Module\Content\Application\Service\PageBlockView;
use App\Module\Content\UI\Web\TwigBlockRenderer;
use App\Module\Media\Domain\Entity\MediaAsset;
use App\Module\Media\Domain\Repository\MediaAssetRepositoryInterface;
use App\Tests\Support\Database\SchemaTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ResponsiveImageRenderingTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        SchemaTestHelper::recreateSchema($entityManager);

        $asset = new MediaAsset('fence.jpg', 'fence.jpg', '/uploads/media/fence.jpg', 'image/jpeg', 1000, 1600, 900, [
            $this->variant('avif', 'image/avif', 320),
            $this->variant('webp', 'image/webp', 320),
            $this->variant('webp', 'image/webp', 768),
        ]);
        $asset->updateMetadata('Забор из библиотеки', null);
        $asset->updateFocalPoint(40, 60);
        $repository = self::getContainer()->get(MediaAssetRepositoryInterface::class);
        self::assertInstanceOf(MediaAssetRepositoryInterface::class, $repository);
        $repository->save($asset);
    }

    public function testImageBlockRendersPictureWithLazyLoadingBelowTheFold(): void
    {
        $html = $this->render('image', ['image' => '/uploads/media/fence.jpg', 'caption' => 'Подпись'], false);

        self::assertStringContainsString('<picture>', $html);
        self::assertStringContainsString('type="image/avif" srcset="/uploads/media/variants/fence-320.avif 320w"', $html);
        self::assertStringContainsString('type="image/webp" srcset="/uploads/media/variants/fence-320.webp 320w, /uploads/media/variants/fence-768.webp 768w"', $html);
        self::assertStringContainsString('src="/uploads/media/fence.jpg" alt="Забор из библиотеки" width="1600" height="900"', $html);
        self::assertStringContainsString('loading="lazy"', $html);
        self::assertStringContainsString('object-position: 40% 60%', $html);
        self::assertStringNotContainsString('fetchpriority', $html);
        self::assertStringContainsString('Подпись', $html);
    }

    public function testFirstScreenImageIsEagerWithHighPriority(): void
    {
        $html = $this->render('image', ['image' => '/uploads/media/fence.jpg', 'alt' => 'Свой alt'], true);

        self::assertStringContainsString('alt="Свой alt"', $html);
        self::assertStringContainsString('loading="eager"', $html);
        self::assertStringContainsString('fetchpriority="high"', $html);
    }

    public function testAssetAltIsUsedWhenBlockHasNoAlt(): void
    {
        $html = $this->render('text_image', ['title' => 'Заголовок', 'image' => '/uploads/media/fence.jpg'], false);

        self::assertStringContainsString('alt="Забор из библиотеки"', $html);
    }

    public function testUnknownImageFallsBackToPlainLazyImg(): void
    {
        $html = $this->render('image', ['image' => '/uploads/legacy/old.jpg'], false);

        self::assertStringNotContainsString('<picture>', $html);
        self::assertStringContainsString('<img src="/uploads/legacy/old.jpg" alt="Block" class="w-full rounded-2xl object-cover" loading="lazy" decoding="async">', $html);
    }

    public function testHeroBackgroundImageGetsFetchPriorityOnFirstScreenOnly(): void
    {
        $first = $this->render('hero', ['title' => 'Заборы', 'image' => '/uploads/media/fence.jpg'], true);
        $later = $this->render('hero', ['title' => 'Заборы', 'image' => '/uploads/media/fence.jpg'], false);

        self::assertStringContainsString('fetchpriority="high"', $first);
        self::assertStringContainsString('alt=""', $first);
        self::assertStringNotContainsString('fetchpriority', $later);
        self::assertStringContainsString('loading="lazy"', $later);
    }

    public function testHeroWithoutImageIsUnchanged(): void
    {
        $html = $this->render('hero', ['title' => 'Заборы'], true);

        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString('Заборы', $html);
    }

    public function testGalleryAndSliderUseResponsiveImages(): void
    {
        $gallery = $this->render('gallery', ['items' => [
            ['image' => '/uploads/media/fence.jpg', 'caption' => 'Один'],
            ['image' => '/uploads/media/fence.jpg', 'caption' => 'Два', 'alt' => 'Два alt'],
        ]], false);
        self::assertSame(2, substr_count($gallery, '<picture>'));
        self::assertStringContainsString('alt="Два alt"', $gallery);

        $slider = $this->render('slider', ['items' => [
            ['src' => '/uploads/media/fence.jpg', 'title' => 'Первый'],
            ['src' => '/uploads/media/fence.jpg', 'title' => 'Второй'],
        ]], true);
        self::assertSame(1, substr_count($slider, 'fetchpriority="high"'));
        self::assertSame(2, substr_count($slider, '<picture>'));
    }

    /**
     * @param array<string, mixed> $content
     */
    private function render(string $type, array $content, bool $aboveFold): string
    {
        $renderer = self::getContainer()->get(TwigBlockRenderer::class);
        self::assertInstanceOf(TwigBlockRenderer::class, $renderer);

        return $renderer->render(new PageBlockView('b1', $type, 'Block', 0, $content, []), $aboveFold);
    }

    /**
     * @return array<string, mixed>
     */
    private function variant(string $type, string $mimeType, int $width): array
    {
        return [
            'type' => $type,
            'publicPath' => \sprintf('/uploads/media/variants/fence-%d.%s', $width, $type),
            'width' => $width,
            'height' => (int) round($width * 900 / 1600),
            'mimeType' => $mimeType,
            'size' => 10,
        ];
    }
}
