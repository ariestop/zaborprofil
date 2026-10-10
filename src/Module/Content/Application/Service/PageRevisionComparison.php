<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Service;

use App\Module\Content\Application\Service\Diff\PageRevisionDiffer;
use App\Module\Content\Domain\Entity\Page;
use App\Module\Content\Domain\Entity\PageRevision;
use App\Module\Content\Domain\Repository\PageBlockRepositoryInterface;
use App\Module\Content\Domain\Repository\PagePublicationRepositoryInterface;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;
use App\Module\Content\Domain\Repository\PageRevisionRepositoryInterface;
use InvalidArgumentException;
use Throwable;

/**
 * Сравнение ревизий страницы между собой и с текущим (рабочим) состоянием.
 */
final readonly class PageRevisionComparison
{
    public const string CURRENT = 'current';

    public function __construct(
        private PageRepositoryInterface $pages,
        private PageBlockRepositoryInterface $blocks,
        private PageRevisionRepositoryInterface $revisions,
        private PagePublicationRepositoryInterface $publications,
        private ContentId $contentId,
        private PageRevisionSnapshotBuilder $snapshotBuilder,
        private PageRevisionDiffer $differ,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function compare(string $pageId, string $fromId, string $toId = self::CURRENT): array
    {
        $page = $this->pages->get($this->contentId->fromString($pageId));
        $from = $this->resolve($page, $fromId);
        $to = $this->resolve($page, $toId);

        return [
            'pageId' => (string) $page->id(),
            'from' => $this->describe($from, $fromId === self::CURRENT),
            'to' => $this->describe($to, $toId === self::CURRENT),
            ...$this->differ->diff($from, $to),
        ];
    }

    public function hasUnpublishedChanges(Page $page): bool
    {
        $published = $this->publications->findByPage((string) $page->id())?->publishedRevision();
        if ($published === null) {
            return false;
        }

        return $this->differ->diff($published, $this->current($page))['hasChanges'];
    }

    /**
     * {@see hasUnpublishedChanges()} для списка страниц: публикации с ревизиями и блоки загружаются
     * двумя запросами на весь список, а не тремя на каждую опубликованную страницу.
     * Ошибка сравнения одной страницы не ломает остальные: страница получает false, ошибка уходит в $onError.
     *
     * @param list<Page>                      $pages
     * @param callable(Page, Throwable): void $onError
     *
     * @return array<string, bool> id страницы => есть ли неопубликованные правки
     */
    public function unpublishedChangesFor(array $pages, callable $onError): array
    {
        $ids = array_map(static fn (Page $page): string => (string) $page->id(), $pages);
        $publications = $this->publications->findByPages($ids);
        $published = array_values(array_filter(
            $ids,
            static fn (string $id): bool => ($publications[$id] ?? null)?->publishedRevision() !== null,
        ));
        $blocks = $this->blocks->findByPages($published);

        $result = [];
        foreach ($pages as $page) {
            $id = (string) $page->id();
            $revision = ($publications[$id] ?? null)?->publishedRevision();
            if ($revision === null) {
                $result[$id] = false;

                continue;
            }

            try {
                $current = $this->snapshotBuilder->build($page, 0, $blocks[$id] ?? []);
                $result[$id] = $this->differ->diff($revision, $current)['hasChanges'];
            } catch (Throwable $exception) {
                $onError($page, $exception);
                $result[$id] = false;
            }
        }

        return $result;
    }

    private function resolve(Page $page, string $revisionId): PageRevision
    {
        if ($revisionId === self::CURRENT) {
            return $this->current($page);
        }

        $revision = $this->revisions->get($this->contentId->fromString($revisionId));
        if ((string) $revision->page()->id() !== (string) $page->id()) {
            throw new InvalidArgumentException('Revision does not belong to the page.');
        }

        return $revision;
    }

    private function current(Page $page): PageRevision
    {
        return $this->snapshotBuilder->build($page, 0, $this->blocks->findByPage((string) $page->id()));
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(PageRevision $revision, bool $isCurrent): array
    {
        return [
            'id' => $isCurrent ? self::CURRENT : (string) $revision->id(),
            'version' => $isCurrent ? null : $revision->version(),
            'createdAt' => $revision->createdAt()->format(DATE_ATOM),
            'createdBy' => $isCurrent ? null : $revision->createdBy(),
            'comment' => $isCurrent ? null : $revision->comment(),
            'action' => $isCurrent ? null : ($revision->changeSummary()['action'] ?? null),
        ];
    }
}
