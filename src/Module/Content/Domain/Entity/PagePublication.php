<?php

declare(strict_types=1);

namespace App\Module\Content\Domain\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: \App\Module\Content\Infrastructure\Repository\DoctrinePagePublicationRepository::class)]
#[ORM\Table(name: 'content_page_publications')]
#[ORM\UniqueConstraint(name: 'uniq_content_page_publications_page_id', columns: ['page_id'])]
final class PagePublication
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\OneToOne(targetEntity: Page::class)]
    #[ORM\JoinColumn(name: 'page_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Page $page;

    #[ORM\ManyToOne(targetEntity: PageRevision::class)]
    #[ORM\JoinColumn(name: 'current_revision_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?PageRevision $currentRevision = null;

    #[ORM\ManyToOne(targetEntity: PageRevision::class)]
    #[ORM\JoinColumn(name: 'draft_revision_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?PageRevision $draftRevision = null;

    #[ORM\ManyToOne(targetEntity: PageRevision::class)]
    #[ORM\JoinColumn(name: 'published_revision_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?PageRevision $publishedRevision = null;

    #[ORM\ManyToOne(targetEntity: PageRevision::class)]
    #[ORM\JoinColumn(name: 'scheduled_revision_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?PageRevision $scheduledRevision = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $lastPublishedAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $lastUnpublishedAt = null;

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

    public function __construct(Page $page)
    {
        $this->id = new Ulid();
        $this->page = $page;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function page(): Page
    {
        return $this->page;
    }

    public function currentRevision(): ?PageRevision
    {
        return $this->currentRevision;
    }

    public function draftRevision(): ?PageRevision
    {
        return $this->draftRevision;
    }

    public function publishedRevision(): ?PageRevision
    {
        return $this->publishedRevision;
    }

    public function scheduledRevision(): ?PageRevision
    {
        return $this->scheduledRevision;
    }

    public function lastPublishedAt(): ?DateTimeImmutable
    {
        return $this->lastPublishedAt;
    }

    public function lastUnpublishedAt(): ?DateTimeImmutable
    {
        return $this->lastUnpublishedAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function markDraft(PageRevision $revision): void
    {
        $this->draftRevision = $revision;
        $this->currentRevision = $revision;
        $this->touch();
    }

    public function publish(PageRevision $revision): void
    {
        $this->publishedRevision = $revision;
        $this->currentRevision = $revision;
        $this->draftRevision = null;
        $this->scheduledRevision = null;
        $this->lastPublishedAt = new DateTimeImmutable();
        $this->touch();
    }

    public function schedule(PageRevision $revision): void
    {
        $this->scheduledRevision = $revision;
        $this->currentRevision = $revision;
        $this->touch();
    }

    public function unpublish(): void
    {
        $this->lastUnpublishedAt = new DateTimeImmutable();
        $this->touch();
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
