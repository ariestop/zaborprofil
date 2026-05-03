<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Handler;

use App\Module\Content\Application\Command\UpdatePageSeoMetadataCommand;
use App\Module\Content\Application\DTO\PageOutput;
use App\Module\Content\Application\Service\ContentId;
use App\Module\Content\Application\Service\PublicPageCacheInvalidator;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;

final readonly class UpdatePageSeoMetadataHandler
{
    public function __construct(
        private PageRepositoryInterface $pages,
        private ContentId $contentId,
        private PublicPageCacheInvalidator $publicPageCache,
    ) {
    }

    public function __invoke(UpdatePageSeoMetadataCommand $command): PageOutput
    {
        $page = $this->pages->get($this->contentId->fromString($command->id));

        $page->updateSeoMetadata(
            $command->metaDescription,
            $command->canonicalUrl,
            $command->ogTitle,
            $command->ogDescription,
            $command->ogImage,
            $command->ogType,
            $command->jsonLd,
        );

        $this->pages->save($page);

        $this->publicPageCache->invalidate($page->path());

        return PageOutput::fromPage($page);
    }
}
