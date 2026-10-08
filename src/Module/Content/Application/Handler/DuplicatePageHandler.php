<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Handler;

use App\Module\Content\Application\Command\DuplicatePageCommand;
use App\Module\Content\Application\DTO\PageOutput;
use App\Module\Content\Application\Service\ContentId;
use App\Module\Content\Application\Service\CurrentAdminActor;
use App\Module\Content\Domain\Entity\Page;
use App\Module\Content\Domain\Entity\PageBlock;
use App\Module\Content\Domain\Repository\PageBlockRepositoryInterface;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;
use App\Shared\Application\Transaction\TransactionRunnerInterface;
use InvalidArgumentException;

/**
 * Копия страницы: блоки и SEO переносятся, адрес генерируется заново, статус — черновик.
 * Canonical не копируется, чтобы копия не указывала на оригинал как на каноническую страницу.
 */
final readonly class DuplicatePageHandler
{
    private const int MAX_PATH_ATTEMPTS = 100;

    public function __construct(
        private PageRepositoryInterface $pages,
        private PageBlockRepositoryInterface $blocks,
        private ContentId $contentId,
        private CurrentAdminActor $actor,
        private TransactionRunnerInterface $transactions,
    ) {
    }

    public function __invoke(DuplicatePageCommand $command): PageOutput
    {
        // Все записи use case — одной транзакцией: при ошибке на любом шаге не остаётся половины изменений.
        return $this->transactions->run(fn (): PageOutput => $this->handle($command));
    }

    private function handle(DuplicatePageCommand $command): PageOutput
    {
        $sourceId = $this->contentId->fromString($command->id);
        $source = $this->pages->get($sourceId);

        [$path, $slug] = $this->resolveAddress($source, $command);

        $copy = new Page(
            $source->type(),
            $command->title !== null && trim($command->title) !== '' ? $command->title : $source->title() . ' (копия)',
            $slug,
            $path,
            $command->title !== null && trim($command->title) !== '' ? $command->title : $source->h1() . ' (копия)',
            $source->template(),
            $source->sortOrder(),
            $source->isIndexable(),
            $source->parent(),
            $source->visibility(),
            $this->actor->id(),
        );

        $copy->updateSeoMetadata(
            $source->metaDescription(),
            null,
            $source->ogTitle(),
            $source->ogDescription(),
            $source->ogImage(),
            $source->ogType(),
            $source->jsonLd(),
        );
        $copy->updateMetaTitle($source->metaTitle());

        foreach ($this->blocks->findByPage($sourceId) as $block) {
            new PageBlock(
                $copy,
                $block->type(),
                $block->name(),
                $block->position(),
                $block->content(),
                $block->settings(),
                $block->isEnabled(),
                $block->visibility(),
            );
        }

        $this->pages->save($copy);

        return PageOutput::fromPage($copy);
    }

    /**
     * @return array{string, string}
     */
    private function resolveAddress(Page $source, DuplicatePageCommand $command): array
    {
        if ($command->path !== null && trim($command->path) !== '') {
            if ($this->pages->existsByPath($command->path)) {
                throw new InvalidArgumentException('Page path must be unique.');
            }

            return [$command->path, $command->slug !== null && trim($command->slug) !== '' ? $command->slug : $this->suffixed($source->slug(), 1, '-')];
        }

        $trailingSlash = str_ends_with($source->path(), '/') && $source->path() !== '/';
        $basePath = $trailingSlash ? substr($source->path(), 0, -1) : $source->path();
        if ($basePath === '') {
            $basePath = '/home';
        }

        for ($attempt = 1; $attempt <= self::MAX_PATH_ATTEMPTS; ++$attempt) {
            $path = $this->suffixed($basePath, $attempt, '-') . ($trailingSlash ? '/' : '');
            if (!$this->pages->existsByPath($path)) {
                $slug = $command->slug !== null && trim($command->slug) !== '' ? $command->slug : $this->suffixed($source->slug(), $attempt, '-');

                return [$path, $slug];
            }
        }

        throw new InvalidArgumentException('Could not generate a unique path for the page copy.');
    }

    private function suffixed(string $value, int $attempt, string $separator): string
    {
        return $value . $separator . 'copy' . ($attempt > 1 ? $separator . $attempt : '');
    }
}
