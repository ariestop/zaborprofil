<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Handler;

use App\Module\Content\Application\Command\BulkUpdatePagesCommand;
use App\Module\Content\Application\Command\ChangePageStatusCommand;
use App\Module\Content\Application\Service\ContentId;
use App\Module\Content\Application\Service\PublicPageCacheInvalidator;
use App\Module\Content\Domain\Repository\PagePublicationRepositoryInterface;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;
use App\Shared\Domain\Exception\ClientSafeExceptionInterface;
use App\Shared\Domain\Exception\NotFoundExceptionInterface;
use InvalidArgumentException;
use Throwable;
use ValueError;

/**
 * Пакетные операции над страницами. Каждая страница обрабатывается независимо:
 * ошибка одной не отменяет остальные, результат возвращается по каждой странице.
 */
final readonly class BulkUpdatePagesHandler
{
    public const int MAX_PAGES = 100;

    public function __construct(
        private PageRepositoryInterface $pages,
        private PagePublicationRepositoryInterface $publications,
        private ContentId $contentId,
        private ChangePageStatusHandler $changeStatus,
        private PublicPageCacheInvalidator $publicPageCache,
    ) {
    }

    /**
     * @return list<array{id: string, ok: bool, error: string|null}>
     */
    public function __invoke(BulkUpdatePagesCommand $command): array
    {
        $ids = array_values(array_unique($command->ids));
        if ($ids === []) {
            throw new InvalidArgumentException('Select at least one page.');
        }

        if (\count($ids) > self::MAX_PAGES) {
            throw new InvalidArgumentException(\sprintf('Bulk action supports up to %d pages at once.', self::MAX_PAGES));
        }

        $this->assertCommand($command);

        $results = [];
        foreach ($ids as $id) {
            try {
                match ($command->action) {
                    BulkUpdatePagesCommand::ACTION_STATUS => $this->applyStatus($id, $command),
                    default => $this->applyIndexable($id, (bool) $command->indexable),
                };
                $results[] = ['id' => $id, 'ok' => true, 'error' => null];
            } catch (InvalidArgumentException|ValueError|ClientSafeExceptionInterface|NotFoundExceptionInterface $exception) {
                $results[] = ['id' => $id, 'ok' => false, 'error' => $exception->getMessage()];
            } catch (Throwable) {
                $results[] = ['id' => $id, 'ok' => false, 'error' => 'Internal error.'];
            }
        }

        return $results;
    }

    private function assertCommand(BulkUpdatePagesCommand $command): void
    {
        if ($command->action === BulkUpdatePagesCommand::ACTION_STATUS) {
            if ($command->status === null || $command->status === '') {
                throw new InvalidArgumentException('Field "status" is required.');
            }
            if (\in_array($command->status, ['published', 'scheduled'], true)) {
                throw new InvalidArgumentException('Bulk publishing is not supported: publish pages one by one after SEO checks.');
            }

            return;
        }

        if ($command->action === BulkUpdatePagesCommand::ACTION_INDEXABLE) {
            if ($command->indexable === null) {
                throw new InvalidArgumentException('Field "indexable" is required.');
            }

            return;
        }

        throw new InvalidArgumentException('Unknown bulk action.');
    }

    private function applyStatus(string $id, BulkUpdatePagesCommand $command): void
    {
        ($this->changeStatus)(new ChangePageStatusCommand($id, (string) $command->status, $command->comment));
    }

    private function applyIndexable(string $id, bool $indexable): void
    {
        $page = $this->pages->get($this->contentId->fromString($id));
        $page->changeIndexable($indexable);
        $this->pages->save($page);

        $publication = $this->publications->findByPage((string) $page->id());
        $published = $publication?->publishedRevision();
        if ($publication !== null && $published !== null) {
            $published->changeIndexable($indexable);
            $this->publications->save($publication);
        }

        $this->publicPageCache->invalidate($page->path());
    }
}
