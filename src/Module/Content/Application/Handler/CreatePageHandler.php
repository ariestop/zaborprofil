<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Handler;

use App\Module\Content\Application\Command\CreatePageCommand;
use App\Module\Content\Application\DTO\PageOutput;
use App\Module\Content\Application\Service\ContentId;
use App\Module\Content\Application\Service\PageTemplateBlocks;
use App\Module\Content\Domain\Entity\Page;
use App\Module\Content\Domain\Entity\PageTemplate;
use App\Module\Content\Domain\Enum\PageType;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;
use App\Module\Content\Domain\Repository\PageTemplateRepositoryInterface;
use App\Shared\Application\Transaction\TransactionRunnerInterface;
use InvalidArgumentException;

final readonly class CreatePageHandler
{
    public function __construct(
        private PageRepositoryInterface $pages,
        private ContentId $contentId,
        private PageTemplateRepositoryInterface $templates,
        private PageTemplateBlocks $templateBlocks,
        private TransactionRunnerInterface $transactions,
    ) {
    }

    public function __invoke(CreatePageCommand $command): PageOutput
    {
        // Все записи use case — одной транзакцией: при ошибке на любом шаге не остаётся половины изменений.
        return $this->transactions->run(fn (): PageOutput => $this->handle($command));
    }

    private function handle(CreatePageCommand $command): PageOutput
    {
        if ($this->pages->existsByPath($command->path)) {
            throw new InvalidArgumentException('Page path must be unique.');
        }

        $parent = $command->parentId === null ? null : $this->pages->get($this->contentId->fromString($command->parentId));
        $page = new Page(
            PageType::from($command->type),
            $command->title,
            $command->slug,
            $command->path,
            $command->h1,
            $command->template,
            $command->sortOrder,
            $command->isIndexable,
            $parent,
            $command->visibility,
        );

        if ($command->starterTemplate !== null && $command->starterTemplate !== '' && $command->starterTemplate !== 'default') {
            $this->applyStarterTemplate($page, $command->starterTemplate);
        }

        $this->pages->save($page);

        return PageOutput::fromPage($page);
    }

    private function applyStarterTemplate(Page $page, string $code): void
    {
        $template = $this->templates->getByCode($code);
        if (!$template->isActive() || $template->kind() !== PageTemplate::KIND_PAGE) {
            throw new InvalidArgumentException('Page template is not available.');
        }

        $this->templateBlocks->applyTo($page, $template->blocksSchema());

        $seo = $template->defaultSeo();
        if ($seo === []) {
            return;
        }

        $page->updateSeoMetadata(
            $this->optionalString($seo, 'metaDescription'),
            null,
            $this->optionalString($seo, 'ogTitle'),
            $this->optionalString($seo, 'ogDescription'),
            null,
            $this->optionalString($seo, 'ogType'),
            null,
        );
        $page->updateMetaTitle($this->optionalString($seo, 'metaTitle'));
    }

    /**
     * @param array<string, mixed> $values
     */
    private function optionalString(array $values, string $key): ?string
    {
        $value = $values[$key] ?? null;

        return \is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
