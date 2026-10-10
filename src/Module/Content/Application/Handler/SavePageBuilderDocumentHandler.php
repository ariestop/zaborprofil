<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Handler;

use App\Module\Content\Application\Command\SavePageBuilderDocumentCommand;
use App\Module\Content\Application\DTO\BuilderBlockOutput;
use App\Module\Content\Application\DTO\PageBuilderDocumentOutput;
use App\Module\Content\Application\Exception\PageEditConflictException;
use App\Module\Content\Application\Service\BlockSchemaRegistry;
use App\Module\Content\Application\Service\BuilderDocumentVersion;
use App\Module\Content\Application\Service\ContentId;
use App\Module\Content\Application\Service\PublicPageCacheInvalidator;
use App\Module\Content\Application\Service\StructuredBlockDocumentService;
use App\Module\Content\Domain\Entity\PageBlock;
use App\Module\Content\Domain\Repository\PageBlockRepositoryInterface;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;
use App\Module\Content\Domain\ValueObject\PageVisibility;
use App\Shared\Application\Transaction\TransactionRunnerInterface;
use InvalidArgumentException;

/**
 * Сохранение документа конструктора: проверка конфликта правок по версии документа, затем блоки
 * из запроса обновляются, создаются или удаляются, чтобы совпасть с документом.
 */
final readonly class SavePageBuilderDocumentHandler
{
    public function __construct(
        private ContentId $contentId,
        private PageRepositoryInterface $pages,
        private PageBlockRepositoryInterface $blocks,
        private StructuredBlockDocumentService $documentService,
        private PublicPageCacheInvalidator $cacheInvalidator,
        private BuilderDocumentVersion $versions,
        private BlockSchemaRegistry $schemas,
        private TransactionRunnerInterface $transactions,
    ) {
    }

    public function __invoke(SavePageBuilderDocumentCommand $command): PageBuilderDocumentOutput
    {
        // Все записи use case — одной транзакцией: при ошибке на любом шаге не остаётся половины изменений.
        return $this->transactions->run(fn (): PageBuilderDocumentOutput => $this->handle($command));
    }

    /**
     * @throws PageEditConflictException если блоки изменились после версии, от которой начиналась правка
     */
    private function handle(SavePageBuilderDocumentCommand $command): PageBuilderDocumentOutput
    {
        $pageId = $this->contentId->fromString($command->pageId);
        $page = $this->pages->get($pageId);
        $existing = $this->blocks->findByPage($pageId);

        if ($command->baseVersion !== null) {
            $currentItems = array_map(BuilderBlockOutput::fromBlock(...), $existing);
            $currentVersion = $this->versions->fromBlocks($currentItems);
            if (!hash_equals($currentVersion, $command->baseVersion)) {
                throw new PageEditConflictException($currentVersion, $page->updatedAt());
            }
        }

        $existingById = [];
        foreach ($existing as $block) {
            $existingById[(string) $block->id()] = $block;
        }

        $preparedBlocks = [];
        $persist = [];
        $keptIds = [];
        foreach (array_values($command->blocks) as $position => $rawBlock) {
            if (!\is_array($rawBlock)) {
                throw new InvalidArgumentException('Each block must be an object.');
            }

            /** @var array<string, mixed> $stringKeyed */
            $stringKeyed = [];
            foreach ($rawBlock as $key => $value) {
                if (!\is_string($key)) {
                    throw new InvalidArgumentException('Each block must be an object.');
                }
                $stringKeyed[$key] = $value;
            }

            $parsed = $this->documentService->parseBlockPayload($stringKeyed, $position);
            $keptIds[] = $parsed['id'];

            $existingBlock = $existingById[$parsed['id']] ?? null;
            if ($existingBlock instanceof PageBlock) {
                $existingBlock->update(
                    $parsed['type'],
                    $parsed['name'] ?? ($existingBlock->type() === $parsed['type'] ? $existingBlock->name() : $this->schemas->get($parsed['type'])->label),
                    $parsed['content'],
                    $parsed['settings'],
                    $parsed['enabled'],
                    $existingBlock->visibility(),
                );
                $existingBlock->moveTo($position);
                $persist[] = $existingBlock;
                $preparedBlocks[] = BuilderBlockOutput::fromBlock($existingBlock);
                continue;
            }

            $newBlock = new PageBlock(
                $page,
                $parsed['type'],
                $parsed['name'] ?? $this->schemas->get($parsed['type'])->label,
                $position,
                $parsed['content'],
                $parsed['settings'],
                $parsed['enabled'],
                PageVisibility::Public,
            );
            $persist[] = $newBlock;
            $preparedBlocks[] = BuilderBlockOutput::fromBlock($newBlock);
        }

        foreach ($existing as $block) {
            if (!\in_array((string) $block->id(), $keptIds, true)) {
                $this->blocks->remove($block);
            }
        }

        if ($persist !== []) {
            $this->blocks->saveAll($persist);
        }
        $this->cacheInvalidator->invalidate($page->path());

        return PageBuilderDocumentOutput::fromPage($page, $preparedBlocks, $this->versions->fromBlocks($preparedBlocks));
    }
}
