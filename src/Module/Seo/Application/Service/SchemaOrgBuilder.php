<?php

declare(strict_types=1);

namespace App\Module\Seo\Application\Service;

use App\Module\Catalog\Domain\Entity\Product;
use App\Module\Catalog\Domain\Entity\Variant;
use App\Module\Content\Application\Service\PublicPageView;

final readonly class SchemaOrgBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function webPage(PublicPageView $page, string $canonicalUrl): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $page->title,
            'headline' => $page->h1,
            'url' => $canonicalUrl,
        ];

        if ($page->metaDescription !== null) {
            $schema['description'] = $page->metaDescription;
        }

        if ($page->ogImage !== null) {
            $schema['image'] = $page->ogImage;
        }

        return $schema;
    }

    /**
     * @param list<array{label: string, path: string, current: bool}> $breadcrumbs
     *
     * @return array<string, mixed>
     */
    public function breadcrumbList(array $breadcrumbs, string $siteUrl): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(
                fn (array $breadcrumb, int $index): array => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $breadcrumb['label'],
                    'item' => rtrim($siteUrl, '/').'/'.ltrim($breadcrumb['path'], '/'),
                ],
                $breadcrumbs,
                array_keys($breadcrumbs),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function product(Product $product, string $canonicalUrl): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->name(),
            'url' => $canonicalUrl,
        ];

        $description = $product->metaDescription() ?? $product->summary() ?? $product->description();
        if ($description !== null) {
            $schema['description'] = $description;
        }

        if ($product->ogImage() !== null) {
            $schema['image'] = $product->ogImage();
        }

        $offers = array_map(
            static fn (Variant $variant): array => [
                '@type' => 'Offer',
                'name' => $variant->title(),
                'price' => number_format($variant->priceCents() / 100, 2, '.', ''),
                'priceCurrency' => $variant->currency(),
                'availability' => 'https://schema.org/InStock',
                'url' => $canonicalUrl,
            ],
            $product->activeVariants(),
        );

        if ($offers !== []) {
            $schema['offers'] = \count($offers) === 1 ? $offers[0] : $offers;
        }

        return $schema;
    }
}
