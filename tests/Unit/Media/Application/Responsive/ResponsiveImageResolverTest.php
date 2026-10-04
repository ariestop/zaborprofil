<?php

declare(strict_types=1);

namespace App\Tests\Unit\Media\Application\Responsive;

use App\Module\Media\Application\Responsive\ResponsiveImageResolver;
use App\Module\Media\Domain\Entity\MediaAsset;
use App\Module\Media\Domain\Repository\MediaAssetRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class ResponsiveImageResolverTest extends TestCase
{
    public function testResolvesAssetByPublicPathWithSortedSourcesAndMemoizes(): void
    {
        $asset = $this->asset('/uploads/media/fence.webp', 'image/webp', 1600, [
            $this->variant('webp', 'image/webp', 768),
            $this->variant('avif', 'image/avif', 768),
            $this->variant('webp', 'image/webp', 320),
        ]);
        $asset->updateFocalPoint(25, 75);

        $repository = $this->createMock(MediaAssetRepositoryInterface::class);
        $repository->expects(self::once())->method('findByPublicPaths')->with(['/uploads/media/fence.webp'])->willReturn([$asset]);

        $resolver = new ResponsiveImageResolver($repository);
        $image = $resolver->resolve('/uploads/media/fence.webp?v=2');

        self::assertNotNull($image);
        self::assertSame(1600, $image->width);
        self::assertSame(900, $image->height);
        self::assertSame(25, $image->focalX);
        self::assertSame(75, $image->focalY);
        self::assertSame(['image/avif', 'image/webp'], array_map(static fn ($source) => $source->mimeType, $image->sources));
        self::assertSame(
            '/uploads/media/variants/fence-320.webp 320w, /uploads/media/variants/fence-768.webp 768w, /uploads/media/fence.webp 1600w',
            $image->sources[1]->srcset(),
        );
        self::assertSame($image, $resolver->resolve('/uploads/media/fence.webp'));
    }

    public function testUnknownAndExternalSourcesAreNotResolved(): void
    {
        $repository = $this->createMock(MediaAssetRepositoryInterface::class);
        $repository->expects(self::once())->method('findByPublicPaths')->with(['/uploads/media/missing.jpg'])->willReturn([]);

        $resolver = new ResponsiveImageResolver($repository);

        self::assertNull($resolver->resolve('/uploads/media/missing.jpg'));
        self::assertNull($resolver->resolve('/uploads/media/missing.jpg'));
        self::assertNull($resolver->resolve('https://example.test/uploads/media/a.jpg'));
        self::assertNull($resolver->resolve('/images/a.jpg'));
    }

    public function testNonImageAssetsAreIgnored(): void
    {
        $asset = new MediaAsset('price.pdf', 'price.pdf', '/uploads/media/price.pdf', 'application/pdf', 100, null, null);
        $repository = $this->createStub(MediaAssetRepositoryInterface::class);
        $repository->method('findByPublicPaths')->willReturn([$asset]);

        self::assertNull((new ResponsiveImageResolver($repository))->resolve('/uploads/media/price.pdf'));
    }

    /**
     * @param list<array<string, mixed>> $variants
     */
    private function asset(string $publicPath, string $mimeType, int $width, array $variants): MediaAsset
    {
        return new MediaAsset('fence', 'fence', $publicPath, $mimeType, 1000, $width, 900, $variants);
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
