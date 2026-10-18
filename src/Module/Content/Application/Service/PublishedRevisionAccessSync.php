<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Service;

use App\Module\Content\Domain\Entity\Page;
use App\Module\Content\Domain\Repository\PagePublicationRepositoryInterface;

/**
 * Индексация и видимость опубликованной страницы действуют сразу, без переопубликации: это не контент,
 * а доступ к странице. Публичный рендер берёт их из снимка опубликованной ревизии, sitemap и поиск страницы
 * по адресу — из самой страницы, поэтому после изменения на странице снимок обновляется теми же значениями.
 * Иначе robots, sitemap и доступность страницы расходились бы до следующей публикации.
 */
final readonly class PublishedRevisionAccessSync
{
    public function __construct(private PagePublicationRepositoryInterface $publications)
    {
    }

    public function sync(Page $page): void
    {
        $publication = $this->publications->findByPage((string) $page->id());
        $published = $publication?->publishedRevision();
        if ($publication === null || $published === null) {
            return;
        }

        $published->changeIndexable($page->isIndexable());
        $published->changeVisibility($page->visibility());
        $this->publications->save($publication);
    }
}
