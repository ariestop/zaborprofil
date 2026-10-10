<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Handler;

use App\Module\Content\Application\Command\UpdatePageBlockCommand;
use App\Module\Content\Application\DTO\PageBlockOutput;
use App\Module\Content\Application\Service\BlockSchemaRegistry;
use App\Module\Content\Application\Service\ContentId;
use App\Module\Content\Application\Service\PublicPageCacheInvalidator;
use App\Module\Content\Application\Service\StructuredBlockDocumentService;
use App\Module\Content\Domain\Enum\BlockType;
use App\Module\Content\Domain\Repository\PageBlockRepositoryInterface;

final readonly class UpdatePageBlockHandler
{
    public function __construct(
        private PageBlockRepositoryInterface $blocks,
        private ContentId $contentId,
        private PublicPageCacheInvalidator $publicPageCache,
        private BlockSchemaRegistry $blockSchemas,
        private StructuredBlockDocumentService $documentService,
    ) {
    }

    public function __invoke(UpdatePageBlockCommand $command): PageBlockOutput
    {
        $block = $this->blocks->get($this->contentId->fromString($command->id));
        $type = BlockType::from($command->type);
        // Та же очистка, что и в конструкторе: HTML блока rich-text выводится на сайте как разметка.
        $content = $this->documentService->sanitizeContent($type, $command->content);
        $this->blockSchemas->validate($type, $content);
        $block->update($type, $command->name, $content, $command->settings, $command->isEnabled, $command->visibility);
        $this->blocks->save($block);

        $this->publicPageCache->invalidate($block->page()->path());

        return PageBlockOutput::fromBlock($block);
    }
}
