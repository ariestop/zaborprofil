<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Handler;

use App\Module\Content\Application\Command\ChangePageStatusCommand;
use App\Module\Content\Application\DTO\PageOutput;
use App\Module\Content\Application\Service\ContentId;
use App\Module\Content\Application\Service\PublicPageCacheInvalidator;
use App\Module\Content\Domain\Enum\PageStatus;
use App\Module\Content\Domain\Repository\PagePublicationRepositoryInterface;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;
use App\Module\Content\Domain\Service\PageStatusTransitionPolicy;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class ChangePageStatusHandler
{
    public function __construct(
        private PageRepositoryInterface $pages,
        private PagePublicationRepositoryInterface $publications,
        private ContentId $contentId,
        private PageStatusTransitionPolicy $transitions,
        private PublicPageCacheInvalidator $publicPageCache,
        private Security $security,
    ) {
    }

    public function __invoke(ChangePageStatusCommand $command): PageOutput
    {
        $page = $this->pages->get($this->contentId->fromString($command->id));
        $next = PageStatus::from($command->status);
        $roles = array_values($this->security->getUser()?->getRoles() ?? []);
        $this->transitions->assertAllowed($page->status(), $next, $roles);

        match ($next) {
            PageStatus::Review => $page->submitForReview(),
            PageStatus::Approved => $page->approve(),
            PageStatus::Unpublished => $page->unpublish(),
            PageStatus::Archived => $page->archive(),
            PageStatus::Deleted => $page->delete(),
            PageStatus::Draft => $page->restoreToDraft(),
            default => null,
        };

        $this->pages->save($page);
        if (\in_array($next, [PageStatus::Unpublished, PageStatus::Archived, PageStatus::Deleted], true)) {
            $publication = $this->publications->findByPage((string) $page->id());
            if ($publication !== null) {
                $publication->unpublish();
                $this->publications->save($publication);
            }
        }

        $this->publicPageCache->invalidate($page->path());

        return PageOutput::fromPage($page);
    }
}
