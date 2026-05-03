<?php

declare(strict_types=1);

namespace App\Module\Seo\Application\Service;

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
}
