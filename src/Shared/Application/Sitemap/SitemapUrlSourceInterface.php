<?php

declare(strict_types=1);

namespace App\Shared\Application\Sitemap;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Дополнительный источник адресов для sitemap.xml (после страниц и товаров).
 * Модуль публикует свои разделы, не завися от модуля Seo: SitemapBuilder собирает источники по тегу.
 */
#[AutoconfigureTag(self::TAG)]
interface SitemapUrlSourceInterface
{
    public const string TAG = 'app.sitemap_url_source';

    public function countUrls(): int;

    /**
     * @return list<SitemapUrl> адреса в постоянном порядке, чтобы разбиение sitemap на части было стабильным
     */
    public function urls(int $limit, int $offset): array;
}
