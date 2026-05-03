<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Handler;

use App\Module\Content\Application\Command\RollbackPageRevisionCommand;
use App\Module\Content\Application\DTO\PageOutput;
use App\Module\Content\Application\Service\ContentId;
use App\Module\Content\Application\Service\PageRevisionSnapshotBuilder;
use App\Module\Content\Application\Service\PublicPageCacheInvalidator;
use App\Module\Content\Application\Service\SnapshotValueNormalizer;
use App\Module\Content\Domain\Entity\PageBlock;
use App\Module\Content\Domain\Enum\BlockType;
use App\Module\Content\Domain\Enum\PageType;
use App\Module\Content\Domain\Repository\PageBlockRepositoryInterface;
use App\Module\Content\Domain\Repository\PagePublicationRepositoryInterface;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;
use App\Module\Content\Domain\Repository\PageRevisionRepositoryInterface;
use App\Module\Content\Domain\ValueObject\PageVisibility;
use InvalidArgumentException;

final readonly class RollbackPageRevisionHandler
{
    public function __construct(
        private PageRepositoryInterface $pages,
        private PageBlockRepositoryInterface $blocks,
        private PageRevisionRepositoryInterface $revisions,
        private PagePublicationRepositoryInterface $publications,
        private ContentId $contentId,
        private PageRevisionSnapshotBuilder $snapshotBuilder,
        private SnapshotValueNormalizer $normalizer,
        private PublicPageCacheInvalidator $publicPageCache,
    ) {
    }

    public function __invoke(RollbackPageRevisionCommand $command): PageOutput
    {
        $page = $this->pages->get($this->contentId->fromString($command->pageId));
        $revision = $this->revisions->get($this->contentId->fromString($command->revisionId));

        if ((string) $revision->page()->id() !== (string) $page->id()) {
            throw new InvalidArgumentException('Revision does not belong to the page.');
        }

        $page->update(
            PageType::from($revision->type()),
            $revision->title(),
            $revision->slug(),
            $revision->path(),
            $revision->h1(),
            $revision->template(),
            $this->normalizer->int($revision->settingsSnapshot()['sortOrder'] ?? null, $page->sortOrder()),
            (bool) ($revision->seoSnapshot()['isIndexable'] ?? true),
            $page->parent(),
            PageVisibility::from($this->normalizer->string($revision->settingsSnapshot()['visibility'] ?? null, PageVisibility::Public->value)),
        );
        $seo = $revision->seoSnapshot();
        $page->updateSeoMetadata(
            \is_string($seo['metaDescription'] ?? null) ? $seo['metaDescription'] : null,
            \is_string($seo['canonicalUrl'] ?? null) ? $seo['canonicalUrl'] : null,
            \is_string($seo['ogTitle'] ?? null) ? $seo['ogTitle'] : null,
            \is_string($seo['ogDescription'] ?? null) ? $seo['ogDescription'] : null,
            \is_string($seo['ogImage'] ?? null) ? $seo['ogImage'] : null,
            \is_string($seo['ogType'] ?? null) ? $seo['ogType'] : null,
            $this->normalizer->objectListOrNull($seo['jsonLd'] ?? null),
        );

        foreach ($this->blocks->findByPage((string) $page->id()) as $block) {
            $this->blocks->remove($block);
        }

        foreach ($revision->blocksSnapshot() as $blockSnapshot) {
            $block = new PageBlock(
                $page,
                BlockType::from($this->normalizer->string($blockSnapshot['type'] ?? null, BlockType::Text->value)),
                $this->normalizer->string($blockSnapshot['name'] ?? null, 'Block'),
                $this->normalizer->int($blockSnapshot['position'] ?? null),
                $this->normalizer->stringKeyedArray($blockSnapshot['content'] ?? null),
                $this->normalizer->stringKeyedArray($blockSnapshot['settings'] ?? null),
                (bool) ($blockSnapshot['isEnabled'] ?? true),
                PageVisibility::from($this->normalizer->string($blockSnapshot['visibility'] ?? null, PageVisibility::Public->value)),
            );
            $this->blocks->save($block);
        }

        $this->pages->save($page);
        $draftRevision = $this->snapshotBuilder->build(
            $page,
            $this->revisions->nextVersionForPage((string) $page->id()),
            $this->blocks->findByPage((string) $page->id()),
            null,
            'Rollback to revision '.$revision->version(),
            ['action' => 'rollback', 'sourceRevisionId' => (string) $revision->id()],
        );
        $this->revisions->save($draftRevision);
        $publication = $this->publications->getOrCreate($page);
        $publication->markDraft($draftRevision);
        $this->publications->save($publication);
        $this->publicPageCache->invalidate($page->path());

        return PageOutput::fromPage($page);
    }
}
